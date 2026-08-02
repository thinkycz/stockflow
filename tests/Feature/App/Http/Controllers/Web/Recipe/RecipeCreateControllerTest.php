<?php

declare(strict_types=1);

use App\Models\RecipeCategory;
use App\Models\RecipeInstruction;
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
        'variants' => [['name' => 'M', 'instructions' => [
            ['type' => 'action', 'text' => 'First', 'action_key' => 'other', 'icon_group' => 'neutral'],
            ['type' => 'action', 'text' => 'Second', 'action_key' => 'other', 'icon_group' => 'neutral'],
        ]]],
    ])->assertRedirect();

    $this->assertDatabaseHas('recipes', ['name' => 'New Recipe', 'user_id' => $admin->getKey()]);
});

\test('admin can override ingredient icon groups and preserve their order', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($admin);
    $category = Typer::assertInstance(RecipeCategory::query()->firstOrFail(), RecipeCategory::class);

    $this->be($admin, 'users')->post('/recipes', [
        'category_id' => $category->getKey(), 'name' => 'ICON RECIPE', 'note' => null,
        'variants' => [[
            'name' => 'M',
            'instructions' => [
                ['type' => 'ingredient', 'text' => 'Add 20 g sugar', 'action_key' => 'add', 'quantity_value' => '20', 'unit' => 'g', 'ingredient_name' => 'sugar', 'icon_group' => 'fruit'],
                ['type' => 'ingredient', 'text' => 'Add half mango', 'action_key' => 'add', 'quantity_text' => 'half', 'ingredient_name' => 'mango', 'icon_group' => 'neutral'],
                ['type' => 'action', 'text' => 'Mix', 'action_key' => 'mix', 'icon_group' => 'neutral'],
                ['type' => 'action', 'text' => 'Serve', 'action_key' => 'serve', 'icon_group' => 'neutral'],
            ],
        ]],
    ])->assertRedirect();

    $variant = Typer::assertInstance(RecipeVariant::query()->whereHas('recipe', static fn($query) => $query->where('name', 'Icon Recipe'))->firstOrFail(), RecipeVariant::class);
    \expect($variant->getInstructions()->map(static fn(RecipeInstruction $instruction): string => $instruction->getText())->all())
        ->toBe(['Add 20 g sugar', 'Add half mango', 'Mix', 'Serve'])
        ->and($variant->getInstructions()->first()?->getIconGroup())->toBe('fruit')
        ->and($variant->getInstructions()->skip(1)->first()?->getQuantityText())->toBe('half');
});
