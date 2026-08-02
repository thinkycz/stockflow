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

\test('admin sees the latest completed result and attempt count per recipe', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $admin->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->firstOrFail(), Recipe::class);
    $attempt = (new RecipeTestService())->start($limited, $worker, $recipe);
    (new RecipeTestService())->submit($limited, $attempt, \collect($attempt->getCorrectStepsSnapshot())->pluck('token')->all());

    $this->be($admin, 'users')->get('/recipe-test-results?worker_id=' . $worker->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/Results')
        ->assertJsonPath('props.recipes.0.attempt_count', 1);
});
