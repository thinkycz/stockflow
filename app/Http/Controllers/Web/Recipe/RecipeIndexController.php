<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Domain\Recipes\RecipeCatalogRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeIndexController
{
    /**
     * Render the full authored library and lightweight local lookup index.
     */
    public function __invoke(Request $request, RecipeCatalogRepository $catalog): Response
    {
        return Inertia::render('recipes/Index', [
            'categories' => $catalog->categories(), 'lookup' => $catalog->lookupIndex(),
            'recipes' => \array_map(static function (array $recipe): array {
                $recipe['variant_count'] = \count($recipe['variants']);
                unset($recipe['variants']);

                return $recipe;
            }, $catalog->recipes()),
            'filters' => ['search' => \mb_trim($request->string('search')->toString()), 'category' => $request->string('category')->toString()],
        ]);
    }
}
