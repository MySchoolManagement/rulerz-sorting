<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Specification;

use RulerZ\Sorting\SortDirection;
use RulerZ\Spec\AbstractSpecification;

abstract class AbstractSortingSpecification extends AbstractSpecification
{
    /**
    * @var string
    */
    private $alias;

    /**
     * @var string
     */
    private $direction;

    /**
     * @param string      $direction Either SortDirection::ASCENDING or SortDirection::DESCENDING
     * @param string|null $alias
     */
    public function __construct($direction = SortDirection::ASCENDING, string $alias = null)
    {
        $this->direction = $direction;
        $this->alias = $alias;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return [
            $this->direction,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function alias(string $property): string
    {
        return parent::alias($property).' = ?';
    }
}
