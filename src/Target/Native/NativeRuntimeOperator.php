<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\Native;

class NativeRuntimeOperator
{
    /**
     * @var string
     */
    private $callable;

    /**
     * @var array
     */
    private $arguments;

    public function __construct($callable, $arguments)
    {
        $this->callable = $callable;
        $this->arguments = $arguments;
    }

    public function format()
    {
        $formattedArguments = join(',', array_map(function ($argument) {
            if ($argument instanceof NativeRuntimeOperator || $argument instanceof NativeCompileTimeOperator) {
                return $argument->format();
            }

            if ('$' === $argument[0]) {
                return $argument;
            } else {
                return sprintf('"%s"', $argument);
            }
        }, $this->arguments));
        
        return sprintf(
            'new \RulerZ\Sorting\Target\Native\SortOperatorDefinition(%s, [%s])',
            $this->callable,
            $formattedArguments
        );
    }
}
