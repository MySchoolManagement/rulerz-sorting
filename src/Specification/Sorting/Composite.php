<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Specification\Sorting;

use RulerZ\Exception\ParameterOverridenException;
use RulerZ\Spec\Specification;

class Composite implements Specification
{
    /**
     * @var string
     */
    private $operator;

    /**
     * @var array
     */
    private $specifications = [];

    /**
     * Builds a composite specification.
     *
     * @param string $operator       the operator used to join the specifications
     * @param array  $specifications a list of initial specifications
     */
    public function __construct($operator, array $specifications = [])
    {
        $this->operator = $operator;

        foreach ($specifications as $specification) {
            $this->addSpecification($specification);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRule()
    {
        return implode(sprintf('%s ', $this->operator), array_map(function (Specification $specification) {
            return $specification->getRule();
        }, $this->specifications));
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        $parametersCount = 0;

        $parametersList = array_map(function (Specification $specification) use (&$parametersCount) {
            $parametersCount += count($specification->getParameters());

            return $specification->getParameters();
        }, $this->specifications);

        $mergedParameters = call_user_func_array('array_merge', $parametersList);

        // error handling in case of overridden parameters
        if ($parametersCount !== count($mergedParameters)) {
            $overriddenParameters = $this->searchOverriddenParameters($parametersList);

            $specificationsTypes = array_map(function (Specification $spec) {
                return get_class($spec);
            }, $this->specifications);

            throw new ParameterOverridenException(sprintf(
                'Looks like some parameters were overriden (%s) while combining specifications of types %s'."\n".
                'More information on how to solve this can be found here: https://github.com/K-Phoen/rulerz/issues/3',
                implode(', ', $overriddenParameters),
                implode(', ', $specificationsTypes)
            ));
        }

        return $mergedParameters;
    }

    /**
     * Adds a new specification.
     *
     * @param Specification $specification
     */
    public function addSpecification(Specification $specification)
    {
        $this->specifications[] = $specification;
    }

    /**
     * Search the parameters that were overridden during the parameters-merge phase.
     *
     * @param array $parametersList
     *
     * @return array names of the overridden parameters
     */
    private function searchOverriddenParameters(array $parametersList)
    {
        $parametersUsageCount = [];

        foreach ($parametersList as $list) {
            foreach ($list as $parameter => $_value) {
                if (! isset($parametersUsageCount[$parameter])) {
                    $parametersUsageCount[$parameter] = 0;
                }

                $parametersUsageCount[$parameter] += 1;
            }
        }

        $overriddenParameters = array_filter($parametersUsageCount, function ($count) {
            return $count > 1;
        });

        return array_keys($overriddenParameters);
    }
}
