<?php

declare(strict_types=1);

use App\Models\RecipeCategory;
use App\Models\RecipeIngredient;
use App\Models\RecipeVariant;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin creates a structured recipe', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($admin);
    $category = Typer::assertInstance(RecipeCategory::query()->firstOrFail(), RecipeCategory::class);

    $this->be($admin, 'users')->get('/recipes/create', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/Edit');
    $this->be($admin, 'users')->post('/recipes', [
        'category_id' => $category->getKey(), 'name' => 'NEW RECIPE', 'note' => 'Note',
        'variants' => [['name' => 'M', 'steps' => [['text' => 'First'], ['text' => 'Second']]]],
    ])->assertRedirect();

    $this->assertDatabaseHas('recipes', ['name' => 'NEW RECIPE', 'user_id' => $admin->getKey()]);
});

\test('admin can override ingredient icon groups and preserve their order', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($admin);
    $category = Typer::assertInstance(RecipeCategory::query()->firstOrFail(), RecipeCategory::class);

    $this->be($admin, 'users')->post('/recipes', [
        'category_id' => $category->getKey(), 'name' => 'ICON RECIPE', 'note' => null,
        'variants' => [[
            'name' => 'M',
            'ingredients' => [
                ['quantity_value' => '20', 'quantity_text' => null, 'unit' => 'g', 'name' => 'sugar', 'icon_group' => 'fruit', 'source_text' => '20g sugar'],
                ['quantity_value' => null, 'quantity_text' => 'half', 'unit' => null, 'name' => 'mango', 'icon_group' => 'neutral', 'source_text' => 'half mango'],
            ],
            'steps' => [['text' => 'Mix', 'action_key' => 'mix', 'source_text' => 'Mix'], ['text' => 'Serve', 'action_key' => 'serve', 'source_text' => 'Serve']],
        ]],
    ])->assertRedirect();

    $variant = Typer::assertInstance(RecipeVariant::query()->whereHas('recipe', static fn($query) => $query->where('name', 'ICON RECIPE'))->firstOrFail(), RecipeVariant::class);
    \expect($variant->getIngredients()->map(static fn(RecipeIngredient $ingredient): string => $ingredient->getName())->all())->toBe(['sugar', 'mango'])
        ->and($variant->getIngredients()->first()?->getIconGroup())->toBe('fruit')
        ->and($variant->getIngredients()->last()?->getQuantityText())->toBe('half');
});
