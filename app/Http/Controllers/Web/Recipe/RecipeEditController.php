<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Recipe;

use App\Domain\Recipes\RecipeCatalogService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\RecipeValidity;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeInstruction;
use App\Models\RecipeVariant;
use App\Models\User;
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
        $recipe->load('variants.instructions');

        return Inertia::render('recipes/Edit', [
            'recipe' => [
                'id' => $recipe->getKey(), 'category_id' => $recipe->getCategoryId(), 'name' => $recipe->getName(), 'note' => $recipe->getNote(),
                'variants' => $recipe->getVariants()->map(static fn(RecipeVariant $variant): array => [
                    'name' => $variant->getName(),
                    'instructions' => $variant->getInstructions()->map(static fn(RecipeInstruction $instruction): array => [
                        'type' => $instruction->getType(), 'text' => $instruction->getText(), 'action_key' => $instruction->getActionKey(),
                        'quantity_value' => $instruction->getQuantityValue(), 'quantity_text' => $instruction->getQuantityText(),
                        'unit' => $instruction->getUnit(), 'ingredient_name' => $instruction->getIngredientName(),
                        'target' => $instruction->getTarget(), 'icon_group' => $instruction->getIconGroup(),
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
            'variants.*.name' => $validity->variantName()->nullable()->toArray(),
            'variants.*.instructions' => $validity->instructions()->required()->toArray(),
            'variants.*.instructions.*.type' => $validity->instructionType()->required()->toArray(),
            'variants.*.instructions.*.text' => $validity->stepText()->required()->toArray(),
            'variants.*.instructions.*.action_key' => $validity->actionKey()->required()->toArray(),
            'variants.*.instructions.*.quantity_value' => $validity->ingredientQuantity()->nullable()->toArray(),
            'variants.*.instructions.*.quantity_text' => $validity->ingredientQuantityText()->nullable()->toArray(),
            'variants.*.instructions.*.unit' => $validity->ingredientUnit()->nullable()->toArray(),
            'variants.*.instructions.*.ingredient_name' => $validity->ingredientName()->nullable()->toArray(),
            'variants.*.instructions.*.target' => $validity->instructionTarget()->nullable()->toArray(),
            'variants.*.instructions.*.icon_group' => $validity->ingredientIconGroup()->required()->toArray(),
        ]);
        $variants = [];
        foreach ($validated->assertArray('variants') as $value) {
            $row = Typer::assertStringKeyArray(Typer::assertArray($value));
            $instructions = [];
            foreach (Typer::assertArray($row['instructions'] ?? null) as $instructionValue) {
                $instruction = Typer::assertStringKeyArray(Typer::assertArray($instructionValue));
                $instructions[] = [
                    'type' => Typer::assertString($instruction['type'] ?? null),
                    'text' => \mb_trim(Typer::assertString($instruction['text'] ?? null)),
                    'action_key' => Typer::assertString($instruction['action_key'] ?? 'other'),
                    'quantity_value' => $instruction['quantity_value'] ?? null,
                    'quantity_text' => $instruction['quantity_text'] ?? null,
                    'unit' => $instruction['unit'] ?? null,
                    'ingredient_name' => $instruction['ingredient_name'] ?? null,
                    'target' => $instruction['target'] ?? null,
                    'icon_group' => Typer::assertString($instruction['icon_group'] ?? 'neutral'),
                ];
            }
            $variantName = isset($row['name']) ? \mb_trim(Typer::assertString($row['name'])) : '';
            $variants[] = ['name' => $variantName !== '' ? $variantName : null, 'instructions' => $instructions];
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
