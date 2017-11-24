<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Compiler;

/**
 * Represents a compilation target.
 */
interface SortingCompilationTarget
{
    const MODE_SORT = 'sort';
    const MODE_APPLY_SORT = 'apply_sort';
}
