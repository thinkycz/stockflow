<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('admin can delete their own shift', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
    ]);

    $this->be($admin, 'users')
        ->delete("/shifts/{$shift->getKey()}")
        ->assertRedirect();

    \expect(Shift::query()->whereKey($shift->getKey())->exists())->toBeFalse();
});

\test('cannot delete a shift belonging to another admin', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $userB->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $userB->getKey()]);
    $foreign = Shift::factory()->create([
        'user_id' => $userB->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
    ]);

    $this->be($userA, 'users')
        ->delete("/shifts/{$foreign->getKey()}")
        ->assertNotFound();

    \expect(Shift::query()->whereKey($foreign->getKey())->exists())->toBeTrue();
});
