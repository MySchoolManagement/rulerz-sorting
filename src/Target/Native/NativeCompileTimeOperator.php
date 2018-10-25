<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\Native;

class NativeCompileTimeOperator
{
    /**
     * @var string
     */
    private $compiledOperator;

    public function __construct($compiled)
    {
        $this->compiledOperator = $compiled;
    }

    public function format()
    {
        return $this->compiledOperator;
    }
}
