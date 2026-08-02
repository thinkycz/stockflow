<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin and limited accounts can browse the company recipe catalog', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Tea', 'last_name' => 'Worker']);

    $this->be($admin, 'users')->get('/recipes', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/Index')->assertJsonPath('props.is_admin', true);

    $this->be($limited, 'users')->get('/recipes', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'recipes/Index')->assertJsonPath('props.is_admin', false)
        ->assertJsonPath('props.recipes.total', 49)
        ->assertJsonPath('props.workers.0.name', 'Tea Worker')
        ->assertJsonPath('props.testable_recipe_count', 49)
        ->assertJsonPath('props.recipes.data.0.name', 'Classic Matcha Latte')
        ->assertJsonMissingPath('props.recipes.data.0.variants')
        ->assertJsonMissingPath('props.recipes.data.0.note');
});
