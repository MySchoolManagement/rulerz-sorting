<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\Native;

use RulerZ\Sorting\Compiler\SortingCompilationTarget;
use RulerZ\Compiler\Context;
use RulerZ\Target\AbstractCompilationTarget;
use RulerZ\Target\Native\NativeOperators;
use RulerZ\Target\Operators\Definitions;

class Native extends AbstractCompilationTarget
{
    /**
     * {@inheritdoc}
     */
    public function supports($target, $mode)
    {
        if ($mode === SortingCompilationTarget::MODE_APPLY_SORT) {
            return false;
        }

        if ($mode === SortingCompilationTarget::MODE_SORT) {
            return is_array($target) || $target instanceof \Traversable;
        }

        return is_array($target) || is_object($target);
    }

    /**
     * {@inheritdoc}
     */
    protected function getExecutorTraits()
    {
        return [
            '\RulerZ\Sorting\Executor\ArrayTarget\SortTrait',
            '\RulerZ\Executor\ArrayTarget\SatisfiesTrait',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function createVisitor(Context $context)
    {
        return new NativeVisitor($this->getOperators());
    }

    /**
     * {@inheritdoc}
     */
    public function getOperators()
    {
        $operators = NativeOperators::create(parent::getOperators());
        $operators = $operators->mergeWith(
            new Definitions([], [
                '=' => function ($a, $b) {
                    return $a;
                },
            ])
        );

        return $operators;
    }
}
