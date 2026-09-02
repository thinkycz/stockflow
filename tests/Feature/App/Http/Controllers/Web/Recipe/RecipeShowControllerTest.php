<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\Store;
use App\Models\User;
use App\Services\RecipeCatalogService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('both roles can read an active recipe without test-session data', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->firstOrFail(), Recipe::class);

    $this->be($admin, 'users')->get('/recipes/' . $recipe->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.is_admin', true);
    $this->be($limited, 'users')->get('/recipes/' . $recipe->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonMissingPath('props.workers')
        ->assertJsonPath('props.recipe.variants.0.instructions.0.text', 'Add 100 ml milk to serving cup.')
        ->assertJsonPath('props.recipe.variants.0.topping_adjustments.base_toppings', '0–1')
        ->assertJsonPath('props.recipe.variants.0.topping_adjustments.components.0.ingredient_name', 'liquid sugar')
        ->assertJsonPath('props.recipe.variants.0.topping_adjustments.components.0.base_quantity', 20)
        ->assertJsonPath('props.recipe.variants.0.topping_adjustments.components.0.two_toppings_quantity', 15)
        ->assertJsonPath('props.recipe.variants.0.topping_adjustments.components.0.three_toppings_quantity', 10)
        ->assertJsonPath('props.recipe.variants.1.topping_adjustments.components.0.base_quantity', 25)
        ->assertJsonMissingPath('props.recipe.variants.0.ingredients')
        ->assertJsonMissingPath('props.recipe.variants.0.steps');

    $recipe->update(['archived_at' => \now()]);
    $this->be($limited, 'users')->get('/recipes/' . $recipe->getKey())->assertNotFound();
});
