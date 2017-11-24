<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Executor\Polyfill;

use RulerZ\Context\ExecutionContext;

trait SortBasedSatisfaction
{
    /**
     * {@inheritdoc}
     */
    abstract public function sort($target, array $parameters, array $operators, ExecutionContext $context);

    /**
     * {@inheritdoc}
     */
    public function satisfies($target, array $parameters, array $operators, ExecutionContext $context)
    {
        return count($this->sort($target, $parameters, $operators, $context)) !== 0;
    }
}
