<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\RecipeTestAttempt;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\RecipeCatalogService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('limited account starts and submits a recipe test for a selected worker', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $admin->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->where('user_id', $admin->getKey())->firstOrFail(), Recipe::class);

    $this->be($limited, 'users')->post('/recipe-tests', [
        'recipe_id' => $recipe->getKey(), 'worker_id' => $worker->getKey(),
    ])->assertRedirect();

    $attempt = Typer::assertInstance(RecipeTestAttempt::query()->firstOrFail(), RecipeTestAttempt::class);
    $this->be($limited, 'users')->get('/recipe-tests/' . $attempt->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/Test');

    $tokens = \collect($attempt->getCorrectStepsSnapshot())->pluck('token')->all();
    $this->be($limited, 'users')->put('/recipe-tests/' . $attempt->getKey(), ['tokens' => $tokens], $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.result.passed', true)->assertJsonPath('props.result.score', 100);
});

\test('admin cannot create personnel attempts and foreign accounts cannot open them', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->where('user_id', $admin->getKey())->firstOrFail(), Recipe::class);

    $this->be($admin, 'users')->post('/recipe-tests', [
        'recipe_id' => $recipe->getKey(), 'worker_id' => $worker->getKey(),
    ])->assertForbidden();

    $this->be($limited, 'users')->post('/recipe-tests', [
        'recipe_id' => $recipe->getKey(), 'worker_id' => $worker->getKey(),
    ])->assertRedirect();
    $attempt = Typer::assertInstance(RecipeTestAttempt::query()->firstOrFail(), RecipeTestAttempt::class);
    $foreign = UserFactory::new()->admin()->createOne();
    $this->be($foreign, 'users')->get('/recipe-tests/' . $attempt->getKey())->assertNotFound();
});
