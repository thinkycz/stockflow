<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('admin can delete their own worker', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')
        ->delete("/workers/{$worker->getKey()}")
        ->assertRedirect('/workers');

    \expect(Worker::query()->whereKey($worker->getKey())->exists())->toBeFalse();
});

\test('cannot delete a worker belonging to another admin', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    $foreign = Worker::factory()->create(['user_id' => $userB->getKey()]);

    $this->be($userA, 'users')
        ->delete("/workers/{$foreign->getKey()}")
        ->assertNotFound();

    \expect(Worker::query()->whereKey($foreign->getKey())->exists())->toBeTrue();
});

\test('cannot delete a worker with existing shifts', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
    ]);

    $this->be($admin, 'users')
        ->delete("/workers/{$worker->getKey()}")
        ->assertRedirect('/workers');

    \expect(Worker::query()->whereKey($worker->getKey())->exists())->toBeTrue();
});
