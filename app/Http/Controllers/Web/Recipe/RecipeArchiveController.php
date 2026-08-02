<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class RecipeArchiveController
{
    use ValidatesWebRequests;

    /**
     * Archive or restore a company recipe.
     */
    public function __invoke(Request $request, Recipe $recipe): RedirectResponse
    {
        $owner = User::mustAuth();
        $validity = RecipeValidity::inject($owner->getKey());
        $validated = $this->validateRequest($request, ['archived' => $validity->archived()->required()->toArray()]);
        $archived = $validated->assertBool('archived');
        (new RecipeCatalogService())->setArchived($owner, $recipe, $archived);
        Inertia::flash('success', $archived ? \__('Recipe archived.') : \__('Recipe restored.'));

        return Resolver::resolveRedirector()->route('recipes.index', ['archived' => $archived]);
    }
}
