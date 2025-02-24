<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Executor\ArrayTarget;

use RulerZ\Context\ExecutionContext;
use RulerZ\Context\ObjectContext;
use RulerZ\Executor\Executor;
use RulerZ\Result\IteratorTools;
use RulerZ\Sorting\Target\Native\SortOperatorDefinition;

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
        $sortingFields = $this->execute($target, $operators, $parameters);
        $target = is_array($target) ? $target : iterator_to_array($target);

        usort($target, function ($a, $b) use ($sortingFields, $parameters) {
            $t = [true => -1, false => 1];
            $r = true;
            $k = 1;

            $a = new ObjectContext($a);
            $b = new ObjectContext($b);
            
            for ($i = 0; $i < count($sortingFields); $i++) {
                $k = $parameters[$i] === \RulerZ\Sorting\SortDirection::ASCENDING ? 1 : -1;

                if ($sortingFields[$i] instanceof SortOperatorDefinition) {
                    $operator = $sortingFields[$i]->getOperator();
                    $operatorValuesA = [];
                    $operatorValuesB = [];

                    foreach ($sortingFields[$i]->getProperties() as $defProps) {
                        if ($defProps instanceof Executor) {
                            continue;
                        }

                        $operatorValuesA[] = $this->getValue($a, $defProps);
                        $operatorValuesB[] = $this->getValue($b, $defProps);
                    }

                    $aVal = call_user_func($operator, $operatorValuesA);
                    $bVal = call_user_func($operator, $operatorValuesB);
                } else {
                    $aVal = $this->getValue($a, $sortingFields[$i]);
                    $bVal = $this->getValue($b, $sortingFields[$i]);
                }
                
                $r = $aVal < $bVal;

                if ($aVal !== $bVal) {
                    return $t[$r] * $k;
                }
            }

            return $t[$r] * $k;
        });

        // and return the appropriate result type
        if (is_array($target)) {
            return IteratorTools::fromArray($target);
        }

        throw new \RuntimeException(sprintf('Unhandled result type: "%s"', get_class($target)));
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
    
    private function getValue($target, array $propertyList)
    {
        if (! is_array($propertyList)) {
            return $propertyList;
        }

        $val = $target;

        for ($i = 0; $i < count($propertyList); $i++) {
            if (is_array($val instanceof ObjectContext ? $val->getObject() : $val)) {
                $property = sprintf('[%s]', $propertyList[$i]);
            } else {
                $property = $propertyList[$i];
            }

            $val = $val[$property];
        }

        return $val;
    }
}
