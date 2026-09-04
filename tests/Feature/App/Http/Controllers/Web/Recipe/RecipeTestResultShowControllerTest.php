<?php

declare(strict_types=1);

use App\Domain\Recipes\RecipeCatalogService;
use App\Domain\Recipes\RecipeTestService;
use App\Models\Recipe;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
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
        ->assertOk()->assertJsonPath('props.attempt.worker_name', $worker->getFullName())
        ->assertJsonPath('props.attempt.correct_step_details.0.type', 'ingredient');
    $this->be($limited, 'users')->get('/recipe-test-results/' . $attempt->getKey())->assertRedirect('/dashboard');
});

\test('admin result keeps rendering a legacy structured snapshot', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $worker = Typer::assertInstance(Worker::factory()->createOne(['user_id' => $admin->getKey()]), Worker::class);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->firstOrFail(), Recipe::class);
    $attempt = (new RecipeTestService())->start($limited, $worker, $recipe);
    (new RecipeTestService())->submit($limited, $attempt, \collect($attempt->getCorrectStepsSnapshot())->pluck('token')->all());
    $attempt->setAttribute('variant_snapshot', [
        'ingredients' => [['name' => 'legacy milk', 'icon_group' => 'water_milk']],
        'steps' => [[
            'token' => $attempt->getCorrectStepsSnapshot()[0]['token'],
            'text' => $attempt->getCorrectStepsSnapshot()[0]['text'],
            'action_key' => 'other',
        ]],
    ]);
    $attempt->save();

    $this->be($admin, 'users')->get('/recipe-test-results/' . $attempt->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.attempt.ingredients.0.name', 'legacy milk')
        ->assertJsonPath('props.attempt.correct_step_details.0.action_key', 'other');
});
