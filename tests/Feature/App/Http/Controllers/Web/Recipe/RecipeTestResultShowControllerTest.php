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

\test('only admin can inspect a submitted recipe attempt snapshot', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $admin->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->firstOrFail(), Recipe::class);
    $attempt = (new RecipeTestService())->start($limited, $worker, $recipe);
    (new RecipeTestService())->submit($limited, $attempt, \collect($attempt->getCorrectStepsSnapshot())->pluck('token')->all());

    $this->be($admin, 'users')->get('/recipe-test-results/' . $attempt->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.attempt.worker_name', $worker->getFullName());
    $this->be($limited, 'users')->get('/recipe-test-results/' . $attempt->getKey())->assertRedirect('/dashboard');
});
