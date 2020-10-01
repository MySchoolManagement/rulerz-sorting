<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\DoctrineORM;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use RulerZ\Compiler\Context;
use RulerZ\Target\AbstractSqlTarget;
use RulerZ\Target\DoctrineORM\DoctrineORMVisitor;
use RulerZ\Target\Operators\Definitions;
use RulerZ\Target\Operators\OperatorTools;

class SortingDoctrineORM extends AbstractSqlTarget
{
    /**
     * {@inheritdoc}
     */
    public function supports($target, $mode): bool
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
    public function getOperators(): Definitions
    {
        $operators = parent::getOperators();
        $operators = $operators->mergeWith(
            new Definitions([], [
                'and' => function ($a, $b) {
                    return sprintf('%s', OperatorTools::inlineMixedInstructions([$a, $b], 'AND'));
                }
            ])
        );

        return $operators;
    }

    /**
     * {@inheritdoc}
     */
    public function createCompilationContext($target): Context
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

    /**
     * {@inheritdoc}
     */
    public function getRuleIdentifierHint(string $rule, Context $context): string
    {
        $aliases = implode('', $context['root_aliases']);
        $entities = implode('', $context['root_entities']);
        $joined = '';

        /** @var Join[] $joins */
        foreach ($context['joins'] as $rootEntity => $joins) {
            foreach ($joins as $join) {
                $joined .= $join->getAlias().$join->getJoin();
            }
        }

        return $aliases.$entities.$joined;
    }
}
