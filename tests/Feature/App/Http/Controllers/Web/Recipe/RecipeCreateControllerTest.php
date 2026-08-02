<?php

declare(strict_types=1);

use App\Models\RecipeCategory;
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
