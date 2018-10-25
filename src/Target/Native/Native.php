<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\Native;

use RulerZ\Model\Executor;
use RulerZ\Model\Rule;
use RulerZ\Sorting\Compiler\SortingCompilationTarget;
use RulerZ\Compiler\Context;
use RulerZ\Target\AbstractCompilationTarget;
use RulerZ\Target\Native\NativeOperators;
use RulerZ\Target\Operators\CompileTimeOperator;
use RulerZ\Target\Operators\Definitions;
use RulerZ\Target\Operators\RuntimeOperator;

class Native extends AbstractCompilationTarget
{
    /**
     * {@inheritdoc}
     */
    public function supports($target, $mode): bool
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
            '\RulerZ\Executor\ArrayTarget\ArgumentUnwrappingTrait',
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
    public function getOperators(): Definitions
    {
        $operators = NativeOperators::create(parent::getOperators());
        $operators = $operators->mergeWith(
            new Definitions([], [
                '=' => function ($a, $b) {
                    return $a;
                },
                'and' => function ($a, $b) {
                    return sprintf('%s', SortOperatorTools::inlineMixedInstructions([$a, $b], ', ', false));
                },
            ])
        );

        return $operators;
    }

    /**
     * {@inheritdoc}
     */
    public function compile(Rule $rule, Context $compilationContext): Executor
    {
        $visitor = $this->createVisitor($compilationContext);
        $compiledCode = $visitor->visit($rule);

        if ($compiledCode instanceof NativeCompileTimeOperator 
            || $compiledCode instanceof NativeRuntimeOperator
            || $compiledCode instanceof RuntimeOperator
            || $compiledCode instanceof CompileTimeOperator) {
            $compiledCode = $compiledCode->format(false);
        }

        return new Executor(
            $this->getExecutorTraits(),
            $compiledCode,
            $visitor->getCompilationData()
        );
    }
}
