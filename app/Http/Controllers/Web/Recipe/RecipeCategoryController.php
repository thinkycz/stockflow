<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\RecipeCategory;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeCategoryController
{
    use ValidatesWebRequests;

    /**
     * Display the dedicated category administration page.
     */
    public function index(): Response
    {
        $owner = User::mustAuth();
        (new RecipeCatalogService())->initialize($owner);

        return Inertia::render('recipes/Categories', [
            'categories' => RecipeCategory::query()->where('user_id', $owner->getKey())
                ->withCount('recipes')->orderBy('position')->get()
                ->map(static fn(RecipeCategory $category): array => [
                    'id' => $category->getKey(), 'name' => $category->getName(),
                    'recipes_count' => Typer::assertInt($category->getAttribute('recipes_count')),
                ])->all(),
        ]);
    }

    /**
     * Create a recipe category.
     */
    public function store(Request $request): RedirectResponse
    {
        $owner = User::mustAuth();
        (new RecipeCatalogService())->createCategory($owner, $this->name($request, $owner));
        Inertia::flash('success', \__('Recipe category created.'));

        return Resolver::resolveRedirector()->route('recipe-categories.index');
    }

    /**
     * Rename a recipe category.
     */
    public function update(Request $request, RecipeCategory $recipeCategory): RedirectResponse
    {
        $owner = User::mustAuth();
        (new RecipeCatalogService())->updateCategory($owner, $recipeCategory, $this->name($request, $owner));
        Inertia::flash('success', \__('Recipe category saved.'));

        return Resolver::resolveRedirector()->route('recipe-categories.index');
    }

    /**
     * Delete an empty recipe category.
     */
    public function destroy(RecipeCategory $recipeCategory): RedirectResponse
    {
        if (!(new RecipeCatalogService())->deleteCategory(User::mustAuth(), $recipeCategory)) {
            Inertia::flash('error', \__('A category containing recipes cannot be deleted.'));
        } else {
            Inertia::flash('success', \__('Recipe category deleted.'));
        }

        return Resolver::resolveRedirector()->route('recipe-categories.index');
    }

    /**
     * Move a recipe category one position.
     */
    public function move(Request $request, RecipeCategory $recipeCategory): RedirectResponse
    {
        $validated = $this->validateRequest($request, ['direction' => RecipeValidity::inject()->direction()->required()->toArray()]);
        (new RecipeCatalogService())->moveCategory(User::mustAuth(), $recipeCategory, $validated->assertString('direction'));

        return Resolver::resolveRedirector()->route('recipe-categories.index');
    }

    /**
     * Validate and normalize a category name.
     */
    private function name(Request $request, User $owner): string
    {
        $validity = RecipeValidity::inject($owner->getKey());

        return \mb_trim($this->validateRequest($request, ['name' => $validity->categoryName()->required()->toArray()])->assertString('name'));
    }
}
