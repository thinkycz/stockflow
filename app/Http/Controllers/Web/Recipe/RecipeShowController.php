<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Domain\Recipes\RecipeCatalogRepository;
use Inertia\Inertia;
use Inertia\Response;

class RecipeShowController
{
    /**
     * Resolve a known file recipe without using route input as a file path.
     */
    public function __invoke(string $category, string $recipe, RecipeCatalogRepository $catalog): Response
    {
        return Inertia::render('recipes/Show', [
            'recipe' => $catalog->find($category, $recipe) ?? \abort(404), 'lookup' => $catalog->lookupIndex(),
        ]);
    }
}
