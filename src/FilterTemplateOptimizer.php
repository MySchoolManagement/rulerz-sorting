<?php

declare(strict_types=1);

namespace RulerZ\Sorting;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\QueryBuilder;
use DomainException;
use RulerZ\RulerZ;
use RulerZ\Spec\AndX;
use RulerZ\Spec\Specification;
use Ursula\EntityFramework\Feature\FilterTemplates\Specification\FilterTemplateComposite;
use function Functional\each;
use function Functional\filter;
use function Functional\flat_map;
use function Functional\map;
use function Functional\reindex;

final class FilterTemplateOptimizer
{
    /**
     * @var RulerZ
     */
    private $rulesEngine;

    public function __construct(RulerZ $rulesEngine)
    {
        $this->rulesEngine = $rulesEngine;
    }

    /**
     * This will try to optimize the query by converting the query to a union when it a single FilterTemplateComposite
     * specification. This is because filter templates are OR'd together and this can create some expensive queries.
     *
     * The optimizer will scan the root specifications for an instance of FilterTemplateComposite. If it finds only one
     * and the root specification is an AndX then it will remove it from the specification. It then enters stage2 later
     * in the execution flow.
     *
     * @param QueryBuilder       $target
     * @param Specification|null $filterSpec
     * @param bool               $shouldRunOptimizer
     *
     * @return FilterTemplateOptimizer_State
     */
    public function prepare(QueryBuilder $target, ?Specification $filterSpec, bool $shouldRunOptimizer): FilterTemplateOptimizer_State
    {
        if (null === $filterSpec || ! $shouldRunOptimizer) {
            return new FilterTemplateOptimizer_State($target, $filterSpec, null);
        }

        if (! $filterSpec instanceof FilterTemplateComposite) {
            return new FilterTemplateOptimizer_State($target, $filterSpec, null);
        }

        if ($filterSpec instanceof AndX) {
            $toCheck = $filterSpec->getSpecifications();
        } else {
            $toCheck = [$filterSpec];
        }

        // how many filter composites are there?
        $count = count(filter($toCheck, function (Specification $specification) {
            if ($specification instanceof FilterTemplateComposite) {
                return true;
            } elseif ($specification instanceof AndX && $specification->contains(FilterTemplateComposite::class)) {
                return true;
            }

            return false;
        }));

        // we don't try to optimize more than one
        if (1 !== $count) {
            return new FilterTemplateOptimizer_State($target, $filterSpec, null);
        }

        $composite = null;

        // find the composite and extract it
        $specificationList = map($toCheck, function (Specification $specification) use (&$composite) {
            if ($specification instanceof FilterTemplateComposite) {
                $composite = $specification;

                return null;
            }

            if ($specification instanceof AndX && $specification->contains(FilterTemplateComposite::class)) {
                $remaining = filter($specification->getSpecifications(), function (Specification $specification) use (&$composite) {
                    if ($specification instanceof FilterTemplateComposite) {
                        $composite = $specification;

                        return false;
                    }

                    return true;
                });

                $remaining = array_values($remaining);

                if (0 === count($remaining)) {
                    return null;
                } else {
                    return new AndX($remaining);
                }
            }

            return $specification;
        });

        $specificationList = filter($specificationList, function (?Specification $specification) {
            return null !== $specification;
        });

        if (0 === count($specificationList)) {
            $filterSpec = null;
        } else {
            $filterSpec = new AndX($specificationList);
        }

        return new FilterTemplateOptimizer_State($target, $filterSpec, $composite);
    }

    /**
     * This is the second stage of optimization. This will duplicate $target once for every composite member and produce
     * a UNION query.
     *
     * When this encounters an optimizable query it will produce a NativeQuery and a list of order clauses. It will
     * otherwise return the original target.
     *
     * @param FilterTemplateOptimizer_State $optimizerState
     * @param callable|null                 $unionMemberModifierCallback
     *
     * @return FilterTemplateOptimizer_State
     */
    public function finalize(FilterTemplateOptimizer_State $optimizerState, ?callable $unionMemberModifierCallback = null): FilterTemplateOptimizer_State
    {
        if (! $optimizerState->canBeOptimized()) {
            return new FilterTemplateOptimizer_State($optimizerState->getTarget());
        }

        /** @var QueryBuilder $template */
        $template = clone $optimizerState->getTarget();
        $composite = $optimizerState->getFilterTemplateComposite();

        // WARNING: this will bypass the query cache
        $parser = new Parser($template->getQuery());
        $parserResult = $parser->parse();

        $rsm = $parserResult->getResultSetMapping();

        $orderBy = $this->extractOrderClause($rsm, $template);
        $parameters = [];
        $hasCount = false;

        $unionMembers = map($composite->getSpecifications(), function (Specification $specification) use ($template, $unionMemberModifierCallback, &$parameters, &$hasCount) {
            /** @var QueryBuilder $target */
            $target = $this->rulesEngine->applyFilterSpec(clone $template, $specification);

            // we don't want the order clause inside each union member. that happens in the outer scope by the caller
            $target->resetDQLParts(['having', 'orderBy']);

            if (null !== $unionMemberModifierCallback) {
                $target = $unionMemberModifierCallback($target);
            }

            $query = $target->getQuery();

            // parameters need to be extracted and the correct order maintained. it can happen that the parameters are
            // defined in a different (non-sequential) order in the query.
            $localParametersInOrder = [];

            if (false !== preg_match_all('/\?([0-9]+)/', $query->getDQL(), $localParametersInOrder)) {
                $localParametersInOrder = reindex($localParametersInOrder[1], function (string $parameterName) {
                    return $parameterName;
                });

                each($target->getParameters(), function (Query\Parameter $parameter) use (&$localParametersInOrder) {
                    $localParametersInOrder[$parameter->getName()] = $parameter;
                });

                each($localParametersInOrder, function (Query\Parameter $parameter) use (&$parameters) {
                    $parameters[] = new Query\Parameter(count($parameters), $parameter->getValue(), $parameter->getType());
                });
            }

            $sql = $query->getSQL();

            // this is fragile, but switch to "union all" if there is a count
            if (preg_match('/select.+count.*\(.+from/ims', $sql)) {
//                $hasCount = true;
            }

            return $sql;
        });

        // this is fragile, but switch to "union all" if there is a count

        $query = $template->getEntityManager()->createNativeQuery(
            join($hasCount ? 'UNION ' : ' UNION ', $unionMembers),
            $rsm
        );
        $query->setParameters(new ArrayCollection($parameters));

        return new FilterTemplateOptimizer_State($query, $optimizerState->getFilterSpecification(), $optimizerState->getFilterTemplateComposite(), $orderBy);
    }

    /**
     * @param Query\ResultSetMapping|null $rsm
     * @param QueryBuilder                $template
     *
     * @return array
     */
    private function extractOrderClause(?Query\ResultSetMapping $rsm, QueryBuilder $template): array
    {
        if (empty($rsm->fieldMappings)) {
            $orderBy = [];
        } else {
            $orderBy = flat_map($template->getDQLPart('orderBy'), function (Query\Expr\OrderBy $orderBy) use ($rsm) {
                return map($orderBy->getParts(), function (string $part) use ($rsm) {
                    return preg_replace_callback('/^(.+) (ASC|DESC)$/', function (array $match) use ($rsm) {
                        [$alias, $fieldName] = explode('.', $match[1], 2);

                        foreach ($rsm->fieldMappings as $columnName => $value) {
                            if ($value === $fieldName && $rsm->columnOwnerMap[$columnName] === $alias) {
                                return sprintf('%s %s', $columnName, $match[2]);
                            }
                        }

                        throw new DomainException('This should not be possible');
                    }, $part);
                });
            });
        }

        return $orderBy;
    }

    public function produceQuery(FilterTemplateOptimizer_State $stage2, ?int $limit, ?int $offset): AbstractQuery
    {
        $target = $stage2->getTarget();
        $orderBy = $stage2->getOrderBy();

        if ($target instanceof QueryBuilder) {
            $target
                ->setFirstResult($offset)
                ->setMaxResults($limit);

            return $target->getQuery();
        } elseif ($target instanceof NativeQuery) {
            $parts = filter([
                'ORDER BY' => ! empty($orderBy) ? sprintf(' %s', join(',', $orderBy)) : null,
                'LIMIT' => $limit,
                'OFFSET' => $limit === null ? null : $offset,
            ], function ($value) {
                return null !== $value;
            });

            $sql = sprintf('SELECT * FROM (%s) u %s', $target->getSQL(), join(' ', map($parts, function ($value, string $key) {
                return sprintf('%s %s', $key, $value);
            })));

            $target->setSQL(
                $sql
            );

            return $target;
        }

        throw new DomainException('Unsupported optimizer result');
    }
}

final class FilterTemplateOptimizer_State
{
    /**
     * @var mixed
     */
    private $target;

    /**
     * @var Specification|null
     */
    private $filterSpec;

    /**
     * @var FilterTemplateComposite|null
     */
    private $filterTemplateComposite;

    /**
     * @var string[]
     */
    private $orderBy;

    /**
     * @param mixed                        $target
     * @param Specification|null           $filterSpec
     * @param FilterTemplateComposite|null $composite
     * @param string[]                     $orderBy
     */
    public function __construct($target, ?Specification $filterSpec = null, ?FilterTemplateComposite $composite = null, array $orderBy = [])
    {
        $this->target = $target;
        $this->filterSpec = $filterSpec;
        $this->filterTemplateComposite = $composite;
        $this->orderBy = $orderBy;
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function getFilterSpecification(): ?Specification
    {
        return $this->filterSpec;
    }

    public function getFilterTemplateComposite(): ?FilterTemplateComposite
    {
        return $this->filterTemplateComposite;
    }

    public function canBeOptimized(): bool
    {
        return null !== $this->filterTemplateComposite;
    }

    /**
     * @return string[]
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }
}
