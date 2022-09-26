<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Executor\DoctrineORM;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use RulerZ\Sorting\SortDirection;
use RulerZ\Context\ExecutionContext;
use RulerZ\Result\IteratorTools;

trait SortTrait
{
    abstract protected function execute($target, array $operators, array $parameters);

    /**
     * {@inheritdoc}
     */
    public function applySort($target, array $parameters, array $operators, ExecutionContext $context)
    {
        /* @var \Doctrine\ORM\QueryBuilder $target */
        foreach ($this->detectedJoins as $join) {
            static::leftJoinUnique($target, sprintf('%s.%s', $join['root'], $join['column']), $join['as']);
        }

        // this will return DQL code
        $dql = $this->execute($target, $operators, $parameters)[0];

        // the root alias can not be determined at compile-time so placeholders are left in the DQL
        $dql = str_replace('@@_ROOT_ALIAS_@@', $target->getRootAliases()[0], $dql);

        // FIXME: we hack around to make sorting work within the rules engine. instead of modifying the grammar we do some
        // manipulation
        $dql = str_replace(['='], '', $dql);
        $dql = str_replace(
            array_map(function ($e) { return '?'.$e; }, array_keys($parameters)),
            array_map(function ($e) { return $e === SortDirection::ASCENDING ? 'ASC' : 'DESC'; }, array_values($parameters)),
            $dql
        );

        foreach (explode('AND', $dql) as $order) {
            $order = trim($order);
            $orderWhitespace = strrpos($order, ' ');
            $orderBy = substr($order, 0, $orderWhitespace - 1);
            $orderDirection = substr($order, $orderWhitespace + 1);

            $target->addOrderBy($orderBy, $orderDirection);
        }

        return $target;
    }

    /**
     * {@inheritdoc}
     */
    public function sort($target, array $parameters, array $operators, ExecutionContext $context)
    {
        /* @var \Doctrine\ORM\QueryBuilder $target */

        $this->applySort($target, $parameters, $operators, $context);

        // execute the query
        $result = $target->getQuery()->getResult();

        // and return the appropriate result type
        if ($result instanceof \Traversable) {
            return $result;
        } elseif (is_array($result)) {
            return IteratorTools::fromArray($result);
        }

        throw new \RuntimeException(sprintf('Unhandled result type: "%s"', get_class($result)));
    }

    /**
     * {@inheritdoc}
     */
    public function applyFilter($target, array $parameters, array $operators, ExecutionContext $context)
    {
        throw new \LogicException('Not supported.');
    }

    /**
     * {@inheritdoc}
     */
    public function filter($target, array $parameters, array $operators, ExecutionContext $context)
    {
        throw new \LogicException('Not supported.');
    }

    private static function leftJoinUnique(QueryBuilder $queryBuilder, string $join, string $alias, ?string $conditionType = null, ?string $condition = null, ?string $indexBy = null): QueryBuilder
    {
        if (! self::joinExists($queryBuilder, Join::LEFT_JOIN, $join, $alias, $conditionType, $condition, $indexBy)) {
            $queryBuilder->leftJoin($join, $alias, $conditionType, $condition, $indexBy);
        }

        return $queryBuilder;
    }

    private static function joinExists(QueryBuilder $queryBuilder, string $joinType, string $join, string $alias, ?string $conditionType = null, ?string $condition = null, ?string $indexBy = null): bool
    {
        $existingJoins = $queryBuilder->getDQLPart('join');
        $newJoinAsString = (string) (new Join($joinType, $join, $alias, $conditionType, $condition, $indexBy));

        foreach ($existingJoins as $joins) {
            /** @var Join $join */
            foreach ($joins as $join) {
                if ((string) $join === $newJoinAsString) {
                    return true;
                }
            }
        }

        return false;
    }
}
