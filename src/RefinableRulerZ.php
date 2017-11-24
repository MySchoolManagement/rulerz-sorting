<?php

declare(strict_types=1);

namespace RulerZ\Sorting;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\QueryBuilder;
use RulerZ\Result\IteratorTools;
use RulerZ\RulerZ;
use RulerZ\Spec\Specification;

class RefinableRulerZ
{
    /**
     * @var RulerZ
     */
    private $rulerZ;

    /**
     * @var SortingRulerZ
     */
    private $sortingRulerZ;

    public function __construct(RulerZ $rulerZ, SortingRulerZ $sortingRulerZ)
    {
        $this->rulerZ = $rulerZ;
        $this->sortingRulerZ = $sortingRulerZ;
    }

    public function refineSpec($target, Specification $filterSpec = null, Specification $sortSpec = null, array $executionContext = [], $offset = null, $limit = null)
    {
        $result = $this->applyRefineSpec($target, $filterSpec, $sortSpec, $executionContext, $offset, $limit);

        if ($result instanceof QueryBuilder) {
            return IteratorTools::fromArray($result->getQuery()->getResult());
        }

        return $result;
    }

    public function applyRefineSpec($target, Specification $filterSpec = null, Specification $sortSpec = null, array $executionContext = [], $offset = null, $limit = null)
    {
        $result = $target;

        if ($target instanceof QueryBuilder) {
            if ($offset !== null) {
                $target->setFirstResult($offset);
            }

            if ($limit !== null) {
                $target->setMaxResults($limit);
            }

            if ($sortSpec !== null) {
                $this->sortingRulerZ->applySortSpec($result, $sortSpec, $executionContext);
            }

            if ($filterSpec !== null) {
                $result = $this->rulerZ->applyFilterSpec($result, $filterSpec, $executionContext);
            }
        } else {
            if ($sortSpec !== null) {
                $result = $this->sortingRulerZ->sortSpec($result, $sortSpec, $executionContext);
            }

            if ($filterSpec !== null) {
                $result = $this->rulerZ->filterSpec($result, $filterSpec, $executionContext);
            }

            if (($offset !== null) || ($limit !== null)) {
                if ($result instanceof \Iterator) {
                    // FIXME: this can have performance implications
                    $result = iterator_to_array($result);
                }

                if (is_array($result) === true) {
                    $result = array_slice($result, $offset ?? 0, min($limit ?? count($result), 100));
                } elseif ($result instanceof Collection) {
                    $result = $result->slice($offset ?? 0, $limit ?? $result->count());
                } else {
                    throw new \LogicException('Cannot apply boundary: '.get_class($result));
                }
            }
        }

        return $result;
    }
}
