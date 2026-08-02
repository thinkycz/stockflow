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

class RecipeCreateController
{
    use ValidatesWebRequests;

    /**
     * Display the recipe creation form.
     */
    public function create(): Response
    {
        $owner = User::mustAuth();
        (new RecipeCatalogService())->initialize($owner);

        return Inertia::render('recipes/Edit', ['recipe' => null, 'categories' => $this->categories($owner)]);
    }

    /**
     * Store a structured recipe.
     */
    public function store(Request $request): RedirectResponse
    {
        $owner = User::mustAuth();
        $form = $this->validatedForm($request, $owner);
        $category = Typer::assertInstance(RecipeCategory::query()->where('user_id', $owner->getKey())->whereKey($form['category_id'])->firstOrFail(), RecipeCategory::class);
        $recipe = (new RecipeCatalogService())->save($owner, $category, null, $form['name'], $form['note'], $form['variants']);
        Inertia::flash('success', \__('Recipe created.'));

        return Resolver::resolveRedirector()->route('recipes.show', $recipe->getKey());
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function categories(User $owner): array
    {
        return \array_values(RecipeCategory::query()->where('user_id', $owner->getKey())->orderBy('position')->get()
            ->map(static fn(RecipeCategory $category): array => ['id' => $category->getKey(), 'name' => $category->getName()])->all());
    }

    /**
     * @return array{category_id: int, name: string, note: string|null, variants: list<array{name: string|null, ingredients: list<array<string, mixed>>, steps: list<array<string, mixed>>}>}
     */
    private function validatedForm(Request $request, User $owner): array
    {
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
                $steps[] = [
                    'text' => $text,
                    'action_key' => $step['action_key'] ?? 'other',
                    'source_text' => $step['source_text'] ?? $text,
                ];
            }
            $variantName = isset($row['name']) ? \mb_trim(Typer::assertString($row['name'])) : '';
            $variants[] = ['name' => $variantName !== '' ? $variantName : null, 'ingredients' => $ingredients, 'steps' => $steps];
        }

        return ['category_id' => $validated->assertInt('category_id'), 'name' => \mb_trim($validated->assertString('name')), 'note' => $validated->assertNullableString('note'), 'variants' => $variants];
    }
}
