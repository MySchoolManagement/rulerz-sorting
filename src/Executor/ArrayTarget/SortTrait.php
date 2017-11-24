<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Executor\ArrayTarget;

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
        throw new \LogicException('Not supported.');
    }

    /**
     * {@inheritdoc}
     */
    public function sort($target, array $parameters, array $operators, ExecutionContext $context)
    {
        /** @var array $target */
        $callable = $this->execute($target, $operators, $parameters);

        $result = is_callable($callable) ? call_user_func($callable) : $target;

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
}
