<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\DoctrineORM;

use Doctrine\ORM\QueryBuilder;
use RulerZ\Compiler\Context;
use RulerZ\Target\AbstractSqlTarget;
use RulerZ\Target\DoctrineORM\DoctrineORMVisitor;

class SortingDoctrineORM extends AbstractSqlTarget
{
    /**
     * {@inheritdoc}
     */
    public function supports($target, $mode)
    {
        return $target instanceof QueryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    protected function getExecutorTraits()
    {
        return [
            '\RulerZ\Sorting\Executor\DoctrineORM\SortTrait',
            '\RulerZ\Sorting\Executor\Polyfill\SortBasedSatisfaction',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function createCompilationContext($target)
    {
        /* @var \Doctrine\ORM\QueryBuilder $target */

        return new Context([
            'root_aliases' => $target->getRootAliases(),
            'root_entities' => $target->getRootEntities(),
            'em' => $target->getEntityManager(),
            'joins' => $target->getDQLPart('join'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function createVisitor(Context $context)
    {
        return new DoctrineORMVisitor($context, $this->getOperators(), $this->allowStarOperator);
    }
}
