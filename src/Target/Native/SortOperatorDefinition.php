<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\Native;

class SortOperatorDefinition
{
    /**
     * @var callable
     */
    private $operator;

    /**
     * @var array
     */
    private $properties;

    public function __construct(callable $operator, array $properties)
    {
        $this->operator = $operator;
        $this->properties = $properties;
    }

    public function getOperator(): callable
    {
        return $this->operator;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }
}
