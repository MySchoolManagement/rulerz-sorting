<?php

declare(strict_types=1);

namespace RulerZ\Sorting;

use Countable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use DomainException;
use Iterator;
use LogicException;
use RulerZ\Result\IteratorTools;
use RulerZ\RulerZ;
use RulerZ\Spec\Specification;

class RefinableRulerZ
{
    /**
     * @var RulerZ
     */
    private $rulerZ;

    /**
     * @var SortingRulerZ
     */
    private $sortingRulerZ;

    /**
     * @var FilterTemplateOptimizer
     */
    private $filterTemplateOptimizer;

    public function __construct(RulerZ $rulerZ, SortingRulerZ $sortingRulerZ)
    {
        $this->rulerZ = $rulerZ;
        $this->sortingRulerZ = $sortingRulerZ;
        $this->filterTemplateOptimizer = new FilterTemplateOptimizer($rulerZ);
    }

    public function refineSpec($target, Specification $filterSpec = null, Specification $sortSpec = null, array $executionContext = [], $offset = null, $limit = null, bool $hasApplyAlreadyExecuted = false)
    {
        if ($target instanceof FilterTemplateOptimizer_State && ($filterSpec !== null || $sortSpec !== $sortSpec)) {
            throw new DomainException('$target has already been optimized and $filterSpec or $sortSpec');
        }

        $optimizerResult = $target instanceof FilterTemplateOptimizer_State ? $target : $this->doApplyRefineSpec($target, $filterSpec, $sortSpec, $executionContext, $offset, $limit, true);
        $target = $optimizerResult->getTarget();

        if ($target instanceof QueryBuilder) {
            // we have to remove parts of the query that will conflict with being converted to unions then apply them
            // to the outer scope

            if ($optimizerResult->canBeOptimized()) {
                $target
                    ->setFirstResult(0)
                    ->setMaxResults(null)
                ;
            }

            $finalizeResult = $this->filterTemplateOptimizer->finalize($optimizerResult, null);
            $query = $this->filterTemplateOptimizer->produceQuery($finalizeResult, $limit ?? $target->getMaxResults(), $offset ?? $target->getFirstResult());

            return IteratorTools::fromArray($query->getResult());
        }

        return $target;
    }

    public function refineSpecOne($target, Specification $filterSpec = null, Specification $sortSpec = null, array $executionContext = [], $offset = null, $limit = null)
    {
        $result = $this->refineSpec($target, $filterSpec, $sortSpec, $executionContext, $offset, $limit);
        $result = iterator_to_array($result);

        if (count($result) > 0) {
            return $result[0];
        }

        return null;
    }

    public function applyRefineSpec($target, Specification $filterSpec = null, Specification $sortSpec = null, array $executionContext = [], $offset = null, $limit = null)
    {
        return $this->applyRefineSpecReturnOptimizerResult($target, $filterSpec, $sortSpec, $executionContext, $offset, $limit)->getTarget();
    }

    public function applyRefineSpecReturnOptimizerResult($target, Specification $filterSpec = null, Specification $sortSpec = null, array $executionContext = [], $offset = null, $limit = null)
    {
        $optimizerResult = $this->doApplyRefineSpec($target, $filterSpec, $sortSpec, $executionContext, $offset, $limit, false);

        return $optimizerResult;
    }

    public function count($target, Specification $filterSpec = null, Specification $sortSpec = null, array $executionContext = [], $offset = null, $limit = null): int
    {
        $optimizerResult = $this->doApplyRefineSpec($target, $filterSpec, $sortSpec, $executionContext, $offset, $limit, true);
        $target = $optimizerResult->getTarget();

        if ($target instanceof QueryBuilder) {
            $aliases = $target->getRootAliases();

            $target->select($target->expr()->countDistinct($aliases[0]));

            // FIXME: we have to hack around to get totals to work with fulltext searching. we make a few assumptions here like that
            // we can safely move the MATCH_AGAINST to the WHERE part of the query and drop any column aliases and drop any having parts
            [$fullTextIndex, $fullTextPart] = $this->findFullTextIndex(
                $target->getDQLPart('select')
            );

            if (null !== $fullTextIndex) {
                $target->resetDQLParts(['having', 'orderBy']);
                $target->andWhere($fullTextPart.' > 0');
            }

            $optimizerResult = $this->filterTemplateOptimizer->finalize($optimizerResult, function (QueryBuilder $target) use ($aliases) {
                return $target->select($aliases[0].'.id');
            });

            $target = $optimizerResult->getTarget();

            // creation of the count query is different depending on the result of the optimizer
            if ($target instanceof NativeQuery) {
                // a union was created so we need to manually wrap the union in a count
                $target->setSQL(
                    sprintf('SELECT COUNT(id_0) AS sclr_0 FROM (%s) u', $target->getSQL())
                );
            } elseif ($target instanceof QueryBuilder) {
                $target = $target->getQuery();
            } else {
                throw new DomainException('Unexpected optimizer result');
            }

            $total = (int) $target->getSingleScalarResult();
        } else {
            if (! is_array($target) && ! $target instanceof Countable) {
                // assume generator
                $total = count(iterator_to_array($target));
            } else {
                $total = count($target);
            }
        }

        return $total;
    }

    private function findFullTextIndex(array $parts)
    {
        /* @var Select[] $parts */
        for ($i = 0; $i < count($parts); ++$i) {
            foreach ($parts[$i]->getParts() as $sub) {
                if (false !== stristr($sub, 'MATCH_AGAINST')) {
                    return [$i, mb_substr($sub, 0, mb_strpos($sub, ') AS') + 1)];
                }
            }
        }

        return null;
    }

    private function doApplyRefineSpec($target, ?Specification $filterSpec, ?Specification $sortSpec, array $executionContext, ?int $offset, ?int $limit, bool $shouldRunOptimizer): FilterTemplateOptimizer_State
    {
        $result = $target;

        if ($target instanceof QueryBuilder) {
            $optimizationContext = $this->filterTemplateOptimizer->prepare($target, $filterSpec, $shouldRunOptimizer);
            $target = $optimizationContext->getTarget();
            $filterSpec = $optimizationContext->getFilterSpecification();

            if (null !== $offset) {
                $target->setFirstResult($offset);
            }

            if (null !== $limit) {
                $target->setMaxResults($limit);
            }

            if (null !== $filterSpec) {
                $target = $this->rulerZ->applyFilterSpec($result, $filterSpec, $executionContext);
            }

            if (null !== $sortSpec) {
                $target = $this->sortingRulerZ->applySortSpec($result, $sortSpec, $executionContext);
            }

            return new FilterTemplateOptimizer_State($target, $filterSpec, $optimizationContext->getFilterTemplateComposite());
        } else {
            if (null !== $sortSpec) {
                $result = $this->sortingRulerZ->sortSpec($result, $sortSpec, $executionContext);
            }

            if (null !== $filterSpec) {
                $result = $this->rulerZ->filterSpec($result, $filterSpec, $executionContext);
            }

            if (null !== $offset || null !== $limit) {
                if ($result instanceof Iterator) {
                    // FIXME: this can have performance implications
                    $result = iterator_to_array($result);
                }

                if (true === is_array($result)) {
                    $result = array_slice($result, $offset ?? 0, $limit ?? count($result));
                } elseif ($result instanceof Collection) {
                    $result = $result->slice($offset ?? 0, $limit ?? $result->count());
                } else {
                    throw new LogicException('Cannot apply boundary: '.get_class($result));
                }
            }

            return new FilterTemplateOptimizer_State($result, $filterSpec, null);
        }
    }
}
