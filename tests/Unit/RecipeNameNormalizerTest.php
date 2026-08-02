<?php

declare(strict_types=1);

use App\Support\RecipeNameNormalizer;

\test('recipe names normalize to title case including slash separated names', function (): void {
    \expect(RecipeNameNormalizer::normalize('CLASSIC MATCHA LATTE'))->toBe('Classic Matcha Latte')
        ->and(RecipeNameNormalizer::normalize('MANGO/STRAWBERRY MATCHA LATTE'))->toBe('Mango/Strawberry Matcha Latte');
});
