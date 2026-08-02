<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class RecipeEditController
{
    use ValidatesWebRequests;

    /**
     * Display the structured recipe editor.
     */
    public function edit(Recipe $recipe): Response
    {
        $owner = User::mustAuth();
        $recipe->load(['variants.ingredients', 'variants.steps']);

        return Inertia::render('recipes/Edit', [
            'recipe' => [
                'id' => $recipe->getKey(), 'category_id' => $recipe->getCategoryId(), 'name' => $recipe->getName(), 'note' => $recipe->getNote(),
                'variants' => $recipe->getVariants()->map(static fn(RecipeVariant $variant): array => [
                    'name' => $variant->getName(),
                    'ingredients' => $variant->getIngredients()->map(static fn(RecipeIngredient $ingredient): array => [
                        'quantity_value' => $ingredient->getQuantityValue(), 'quantity_text' => $ingredient->getQuantityText(),
                        'unit' => $ingredient->getUnit(), 'name' => $ingredient->getName(), 'icon_group' => $ingredient->getIconGroup(),
                        'source_text' => $ingredient->getSourceText(),
                    ])->all(),
                    'steps' => $variant->getSteps()->map(static fn(RecipeStep $step): array => [
                        'text' => $step->getText(), 'action_key' => $step->getActionKey(), 'source_text' => $step->getSourceText(),
                    ])->all(),
                ])->all(),
            ],
            'categories' => RecipeCategory::query()->where('user_id', $owner->getKey())->orderBy('position')->get()
                ->map(static fn(RecipeCategory $category): array => ['id' => $category->getKey(), 'name' => $category->getName()])->all(),
        ]);
    }

    /**
     * Replace the editable recipe structure.
     */
    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $owner = User::mustAuth();
        $validity = RecipeValidity::inject($owner->getKey());
        $validated = $this->validateRequest($request, [
            'category_id' => $validity->categoryId()->required()->toArray(), 'name' => $validity->name()->required()->toArray(),
            'note' => $validity->note()->nullable()->toArray(), 'variants' => $validity->variants()->required()->toArray(),
            'variants.*.name' => $validity->variantName()->nullable()->toArray(), 'variants.*.ingredients' => $validity->ingredients()->nullable()->toArray(),
            'variants.*.ingredients.*.quantity_value' => $validity->ingredientQuantity()->nullable()->toArray(),
            'variants.*.ingredients.*.quantity_text' => $validity->ingredientQuantityText()->nullable()->toArray(),
            'variants.*.ingredients.*.unit' => $validity->ingredientUnit()->nullable()->toArray(),
            'variants.*.ingredients.*.name' => $validity->ingredientName()->required()->toArray(),
            'variants.*.ingredients.*.icon_group' => $validity->ingredientIconGroup()->required()->toArray(),
            'variants.*.ingredients.*.source_text' => $validity->sourceText()->nullable()->toArray(),
            'variants.*.steps' => $validity->steps()->required()->toArray(), 'variants.*.steps.*.text' => $validity->stepText()->required()->toArray(),
            'variants.*.steps.*.action_key' => $validity->actionKey()->nullable()->toArray(), 'variants.*.steps.*.source_text' => $validity->sourceText()->nullable()->toArray(),
        ]);
        $variants = [];
        foreach ($validated->assertArray('variants') as $value) {
            $row = Typer::assertStringKeyArray(Typer::assertArray($value));
            $ingredients = [];
            foreach (Typer::assertArray($row['ingredients'] ?? []) as $ingredientValue) {
                $ingredient = Typer::assertStringKeyArray(Typer::assertArray($ingredientValue));
                $ingredients[] = [
                    'quantity_value' => $ingredient['quantity_value'] ?? null,
                    'quantity_text' => $ingredient['quantity_text'] ?? null,
                    'unit' => $ingredient['unit'] ?? null,
                    'name' => \mb_trim(Typer::assertString($ingredient['name'] ?? null)),
                    'icon_group' => Typer::assertString($ingredient['icon_group'] ?? 'neutral'),
                    'source_text' => $ingredient['source_text'] ?? null,
                ];
            }
            $steps = [];
            foreach (Typer::assertArray($row['steps'] ?? null) as $stepValue) {
                $step = Typer::assertStringKeyArray(Typer::assertArray($stepValue));
                $text = \mb_trim(Typer::assertString($step['text'] ?? null));
                $steps[] = ['text' => $text, 'action_key' => $step['action_key'] ?? 'other', 'source_text' => $step['source_text'] ?? $text];
            }
            $variantName = isset($row['name']) ? \mb_trim(Typer::assertString($row['name'])) : '';
            $variants[] = ['name' => $variantName !== '' ? $variantName : null, 'ingredients' => $ingredients, 'steps' => $steps];
        }
        $category = Typer::assertInstance(RecipeCategory::query()->where('user_id', $owner->getKey())->whereKey($validated->assertInt('category_id'))->firstOrFail(), RecipeCategory::class);
        (new RecipeCatalogService())->save($owner, $category, $recipe, \mb_trim($validated->assertString('name')), $validated->assertNullableString('note'), $variants);
        Inertia::flash('success', \__('Recipe saved.'));

        return Resolver::resolveRedirector()->route('recipes.show', $recipe->getKey());
    }

    /**
     * Move a recipe one position within its category.
     */
    public function move(Request $request, Recipe $recipe): RedirectResponse
    {
        $validated = $this->validateRequest($request, ['direction' => RecipeValidity::inject()->direction()->required()->toArray()]);
        (new RecipeCatalogService())->moveRecipe(User::mustAuth(), $recipe, $validated->assertString('direction'));

        return Resolver::resolveRedirector()->route('recipes.index');
    }
}
