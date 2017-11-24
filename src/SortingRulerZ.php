<?php

declare(strict_types=1);

namespace RulerZ\Sorting;

use RulerZ\Sorting\Compiler\SortingCompilationTarget;
use RulerZ\Compiler\Compiler;
use RulerZ\Compiler\CompilationTarget;
use RulerZ\Context\ExecutionContext;
use RulerZ\Exception\TargetUnsupportedException;
use RulerZ\Spec\Specification;

class SortingRulerZ
{
    /**
     * @var array<CompilationTarget>
     */
    private $compilationTargets = [];

    /**
     * Constructor.
     *
     * @param Compiler $compiler           the compiler
     * @param array    $compilationTargets A list of target compilers, each one handles a specific target (an array, a DoctrineQueryBuilder, ...)
     */
    public function __construct(Compiler $compiler, array $compilationTargets = [])
    {
        $this->compiler = $compiler;

        foreach ($compilationTargets as $targetCompiler) {
            $this->registerCompilationTarget($targetCompiler);
        }
    }

    /**
     * Registers a new target compiler.
     *
     * @param CompilationTarget $compilationTarget the target cCompiler to register
     */
    public function registerCompilationTarget(CompilationTarget $compilationTarget)
    {
        $this->compilationTargets[] = $compilationTarget;
    }

    public function sortSpec($target, Specification $spec, array $executionContext = [])
    {
        return $this->sort($target, $spec->getRule(), $spec->getParameters(), $executionContext);
    }

    public function sort($target, $rule, array $parameters = [], array $executionContext = [])
    {
        $targetCompiler = $this->findTargetCompiler($target, SortingCompilationTarget::MODE_SORT);
        $compilationContext = $targetCompiler->createCompilationContext($target);
        $executor = $this->compiler->compile($rule, $targetCompiler, $compilationContext);

        return $executor->sort($target, $parameters, $targetCompiler->getOperators()->getOperators(), new ExecutionContext($executionContext));
    }

    public function applySortSpec($target, Specification $spec, array $executionContext = [])
    {
        return $this->applySort($target, $spec->getRule(), $spec->getParameters(), $executionContext);
    }

    public function applySort($target, $rule, array $parameters = [], array $executionContext = [])
    {
        $targetCompiler = $this->findTargetCompiler($target, SortingCompilationTarget::MODE_APPLY_SORT);
        $compilationContext = $targetCompiler->createCompilationContext($target);
        $executor = $this->compiler->compile($rule, $targetCompiler, $compilationContext);

        return $executor->applySort($target, $parameters, $targetCompiler->getOperators()->getOperators(), new ExecutionContext($executionContext));
    }

    /**
     * Tells if a target satisfies the given rule and parameters.
     * The target compiler to use is determined at runtime using the registered ones.
     *
     * @param mixed  $target           the target
     * @param string $rule             the rule to test
     * @param array  $parameters       the parameters used in the rule
     * @param array  $executionContext the execution context
     *
     * @return bool
     */
    public function satisfies($target, $rule, array $parameters = [], array $executionContext = [])
    {
        $targetCompiler = $this->findTargetCompiler($target, CompilationTarget::MODE_SATISFIES);
        $executor = $this->compiler->compile($rule, $targetCompiler);

        return $executor->satisfies($target, $parameters, $targetCompiler->getOperators(), new ExecutionContext($executionContext));
    }

    /**
     * Tells if a target satisfies the given specification.
     * The target compiler to use is determined at runtime using the registered ones.
     *
     * @param mixed         $target           the target
     * @param Specification $spec             the specification to use
     * @param array         $executionContext the execution context
     *
     * @return bool
     */
    public function satisfiesSpec($target, Specification $spec, array $executionContext = [])
    {
        return $this->satisfies($target, $spec->getRule(), $spec->getParameters(), $executionContext);
    }

    /**
     * Finds a target compiler supporting the given target.
     *
     * @param mixed  $target the target to filter
     * @param string $mode   the execution mode (MODE_FILTER or MODE_SATISFIES)
     *
     * @throws TargetUnsupportedException
     *
     * @return SortingCompilationTarget
     */
    private function findTargetCompiler($target, $mode)
    {
        /** @var SortingCompilationTarget $targetCompiler */
        foreach ($this->compilationTargets as $targetCompiler) {
            if ($targetCompiler->supports($target, $mode)) {
                return $targetCompiler;
            }
        }

        throw new TargetUnsupportedException('The given target is not supported.');
    }
}
