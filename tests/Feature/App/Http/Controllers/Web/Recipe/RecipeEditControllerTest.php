<?php

declare(strict_types=1);

use App\Domain\Recipes\RecipeCatalogService;
use App\Models\Recipe;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin edits a recipe and replaces variants transactionally', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->with('variants.instructions')->firstOrFail(), Recipe::class);

    $this->be($admin, 'users')->get('/recipes/' . $recipe->getKey() . '/edit', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/Edit');
    $this->be($admin, 'users')->put('/recipes/' . $recipe->getKey(), [
        'category_id' => $recipe->getCategoryId(), 'name' => 'EDITED', 'note' => null,
        'variants' => [['name' => null, 'instructions' => [
            ['type' => 'action', 'text' => 'One', 'action_key' => 'other', 'icon_group' => 'neutral'],
            ['type' => 'action', 'text' => 'Two', 'action_key' => 'other', 'icon_group' => 'neutral'],
        ]]],
    ])->assertRedirect();

    \expect($recipe->fresh()?->getName())->toBe('Edited')
        ->and($recipe->fresh()?->variants()->count())->toBe(1);
});

\test('admin changes recipe order inside a category', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    (new RecipeCatalogService())->initialize($admin);
    $first = Typer::assertInstance(Recipe::query()->orderBy('recipe_category_id')->orderBy('position')->firstOrFail(), Recipe::class);
    $second = Typer::assertInstance(Recipe::query()->where('recipe_category_id', $first->getCategoryId())->orderBy('position')->skip(1)->firstOrFail(), Recipe::class);

    $this->be($admin, 'users')->put('/recipes/' . $second->getKey() . '/position', ['direction' => 'up'])->assertRedirect();

    \expect($first->fresh()?->getPosition())->toBe(2)
        ->and($second->fresh()?->getPosition())->toBe(1);
});
