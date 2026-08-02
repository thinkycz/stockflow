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
     * @return array{category_id: int, name: string, note: string|null, variants: list<array{name: string|null, steps: list<string>}>}
     */
    private function validatedForm(Request $request, User $owner): array
    {
        $validity = RecipeValidity::inject($owner->getKey());
        $validated = $this->validateRequest($request, [
            'category_id' => $validity->categoryId()->required()->toArray(), 'name' => $validity->name()->required()->toArray(),
            'note' => $validity->note()->nullable()->toArray(), 'variants' => $validity->variants()->required()->toArray(),
            'variants.*.name' => $validity->variantName()->nullable()->toArray(), 'variants.*.steps' => $validity->steps()->required()->toArray(),
            'variants.*.steps.*.text' => $validity->stepText()->required()->toArray(),
        ]);
        $variants = [];
        foreach ($validated->assertArray('variants') as $value) {
            $row = Typer::assertStringKeyArray(Typer::assertArray($value));
            $steps = [];
            foreach (Typer::assertArray($row['steps'] ?? null) as $stepValue) {
                $step = Typer::assertStringKeyArray(Typer::assertArray($stepValue));
                $steps[] = \mb_trim(Typer::assertString($step['text'] ?? null));
            }
            $variantName = isset($row['name']) ? \mb_trim(Typer::assertString($row['name'])) : '';
            $variants[] = ['name' => $variantName !== '' ? $variantName : null, 'steps' => $steps];
        }

        return ['category_id' => $validated->assertInt('category_id'), 'name' => \mb_trim($validated->assertString('name')), 'note' => $validated->assertNullableString('note'), 'variants' => $variants];
    }
}
