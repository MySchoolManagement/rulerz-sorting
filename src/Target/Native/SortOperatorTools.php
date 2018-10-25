<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\Native;

class SortOperatorTools
{
    public static function inlineMixedInstructions(array $instructions, $operator = null, $useStringBreakingLogic = true)
    {
        $elements = [];

        foreach ($instructions as $instruction) {
            if ($instruction instanceof NativeRuntimeOperator) {
                $elements[] = $instruction->format($useStringBreakingLogic);
            } else if ($instruction instanceof NativeCompileTimeOperator) {
                $elements[] = sprintf('%s', $instruction->format(false));
            } else {
                $elements[] = sprintf('%s', $instruction);
            }
        }

        if (null === $operator) {
            return join('', $elements);
        } else {
            return join(' '.$operator.' ', $elements);
        }
    }
}
