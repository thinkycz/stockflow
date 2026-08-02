<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\RecipeCatalogService;
use App\Services\RecipeTestService;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('limited account can finish a legacy single recipe attempt', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $admin->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->where('user_id', $admin->getKey())->firstOrFail(), Recipe::class);

    $attempt = (new RecipeTestService())->start($limited, $worker, $recipe);
    \expect($attempt->getVariantSnapshot())->not->toBeNull()
        ->and($attempt->getVariantSnapshot()['instructions'] ?? [])->not->toBeEmpty();
    $this->be($limited, 'users')->get('/recipe-tests/' . $attempt->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/Test')
        ->assertJsonCount(8, 'props.attempt.instructions');

    $tokens = \collect($attempt->getCorrectStepsSnapshot())->pluck('token')->all();
    $this->be($limited, 'users')->put('/recipe-tests/' . $attempt->getKey(), ['tokens' => $tokens], $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.result.passed', true)->assertJsonPath('props.result.score', 100);
});

\test('foreign accounts cannot open legacy attempts', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->where('user_id', $admin->getKey())->firstOrFail(), Recipe::class);

    $attempt = (new RecipeTestService())->start($limited, Typer::assertInstance($worker, Worker::class), $recipe);
    $foreign = UserFactory::new()->admin()->createOne();
    $this->be($foreign, 'users')->get('/recipe-tests/' . $attempt->getKey())->assertNotFound();
});
