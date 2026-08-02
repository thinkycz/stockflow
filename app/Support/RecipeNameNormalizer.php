<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class RecipeNameNormalizer
{
    /**
     * Normalize recipe names to title case while preserving punctuation boundaries.
     */
    public static function normalize(string $name): string
    {
        return Str::title(Str::lower(\mb_trim($name)));
    }
}
