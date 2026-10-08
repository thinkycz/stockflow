<?php

declare(strict_types=1);
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('both roles read complete recipes and distinct hot and cold variants', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $limited = UserFactory::new()->limited(Store::factory()->create(['user_id' => $admin->getKey()]))->createOne();
    foreach ([$admin, $limited] as $user) {
        $this->be($user, 'users')->get('/recipes/hot-drinks/strawberry-cloud', $this->inertiaHeaders())
            ->assertOk()->assertJsonPath('component', 'recipes/Show')
            ->assertJsonPath('props.recipe.key', 'hot-drinks/strawberry-cloud')
            ->assertJsonPath('props.recipe.variants.0.ingredients.0.quantity_value', 140)
            ->assertJsonPath('props.recipe.variants.0.ingredients.0.unit', 'g')
            ->assertJsonPath('props.recipe.variants.0.ingredients.2.quantity_value', 3.5)
            ->assertJsonPath('props.recipe.variants.0.ingredients.6.quantity_value', 2)
            ->assertJsonPath('props.recipe.variants.0.ingredients.6.unit', 'pieces')
            ->assertJsonPath('props.recipe.variants.0.steps.1.action_key', 'steam')
            ->assertJsonMissingPath('props.workers');
        $this->be($user, 'users')->get('/recipes/matcha-specials/strawberry-cloud', $this->inertiaHeaders())
            ->assertOk()->assertJsonPath('props.recipe.key', 'matcha-specials/strawberry-cloud')
            ->assertJsonPath('props.recipe.variants.0.selectors.ice', 'with-ice');
    }
});

\test('recipe detail supplies valid preparation links and known timers', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $this->be($admin, 'users')->get('/recipes/hot-drinks/ceylon-milk-tea', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.recipe.related.0.url', '/recipes/preparations/ceylon-milk-tea-preparation');
    $this->be($admin, 'users')->get('/recipes/preparations/ceylon-tea-preparation', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.recipe.variants.0.steps.2.timer_seconds', 600);
    foreach (['/recipes/unknown/classic-matcha', '/recipes/hot-drinks/missing', '/recipes/1'] as $url) {
        $this->be($admin, 'users')->get($url)->assertNotFound();
    }
});
