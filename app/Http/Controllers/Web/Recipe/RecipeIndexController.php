<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeIndexController
{
    public const int TAKE = 60;

    /**
     * Display the searchable company recipe catalog.
     */
    public function __invoke(Request $request): Response
    {
        $actor = User::mustAuth();
        $owner = $actor->resolveScopeUser();
        (new RecipeCatalogService())->initialize($owner);
        $search = \mb_trim($request->string('search')->toString());
        $categoryId = $request->integer('category_id');
        $showArchived = $actor->isAdmin() && $request->boolean('archived');

        $query = Recipe::query()->where('user_id', $owner->getKey())->with(['category', 'variants.ingredients', 'variants.steps']);
        $showArchived ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        if ($search !== '') {
            Recipe::scopeSearch($query, $search);
        }
        if ($categoryId > 0) {
            $query->where('recipe_category_id', $categoryId);
        }
        $paginator = $query->orderBy('recipe_category_id')->orderBy('position')->paginate(self::TAKE)->withQueryString();
        $paginator->through(fn(Recipe $recipe): array => $this->recipeRow($recipe, $actor->isAdmin()));

        $categories = RecipeCategory::query()->where('user_id', $owner->getKey())->withCount([
            'recipes',
            'recipes as active_recipes_count' => static fn(Builder $query): Builder => $query->whereNull('archived_at'),
        ])->orderBy('position')->get()->map(static fn(RecipeCategory $category): array => [
            'id' => $category->getKey(), 'name' => $category->getName(),
            'recipes_count' => Typer::assertInt($category->getAttribute('recipes_count')),
            'active_recipes_count' => Typer::assertInt($category->getAttribute('active_recipes_count')),
        ])->all();

        return Inertia::render('recipes/Index', [
            'is_admin' => $actor->isAdmin(),
            'categories' => $categories,
            'recipes' => Typer::assertStringKeyArray($paginator->toArray()),
            'filters' => ['search' => $search, 'category_id' => $categoryId > 0 ? $categoryId : null, 'archived' => $showArchived],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function recipeRow(Recipe $recipe, bool $isAdmin): array
    {
        return [
            'id' => $recipe->getKey(), 'name' => $recipe->getName(), 'note' => $recipe->getNote(),
            'category' => ['id' => $recipe->getCategoryId(), 'name' => $recipe->getCategory()->getName()],
            'archived' => $recipe->isArchived(),
            'variant_count' => $recipe->getVariants()->count(),
            'variants' => $recipe->getVariants()->map(fn(RecipeVariant $variant): array => [
                'id' => $variant->getKey(),
                'name' => $variant->getName(),
                'ingredients' => $variant->getIngredients()->map(fn(RecipeIngredient $ingredient): array => [
                    'quantity_value' => $ingredient->getQuantityValue(),
                    'quantity_text' => $ingredient->getQuantityText(),
                    'unit' => $ingredient->getUnit(),
                    'name' => $ingredient->getName(),
                    'icon_group' => $ingredient->getIconGroup(),
                    'source_text' => $isAdmin ? $ingredient->getSourceText() : null,
                ])->all(),
                'steps' => $variant->getSteps()->map(fn(RecipeStep $step): array => [
                    'id' => $step->getKey(),
                    'text' => $step->getText(),
                    'action_key' => $step->getActionKey(),
                    'source_text' => $isAdmin ? $step->getSourceText() : null,
                ])->all(),
            ])->all(),
        ];
    }
}
