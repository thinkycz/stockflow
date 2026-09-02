<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\Store;
use App\Models\Worker;
use Carbon\CarbonImmutable;

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

\test('worker with historical shifts is archived without losing history', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => CarbonImmutable::today()->subDay()->toDateString(),
    ]);

    $this->be($admin, 'users')
        ->delete("/workers/{$worker->getKey()}")
        ->assertRedirect('/workers');

    $worker->refresh();
    \expect($worker->isArchived())->toBeTrue()
        ->and(Shift::query()->where('worker_id', $worker->getKey())->exists())->toBeTrue();
});

\test('worker with future shifts is blocked from removal', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => CarbonImmutable::today()->addDay()->toDateString(),
    ]);

    $this->be($admin, 'users')
        ->delete("/workers/{$worker->getKey()}")
        ->assertRedirect('/workers');

    $worker->refresh();
    \expect($worker->isArchived())->toBeFalse();
});

\test('worker with completed attendance is archived and can be restored', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'active_worker_id' => null,
        'ended_at' => CarbonImmutable::now()->subHour(),
    ]);

    $this->be($admin, 'users')->delete("/workers/{$worker->getKey()}")->assertRedirect('/workers');

    $worker->refresh();
    \expect($worker->isArchived())->toBeTrue()
        ->and(AttendanceSession::query()->whereKey($session->getKey())->exists())->toBeTrue();

    $this->be($admin, 'users')->post("/workers/{$worker->getKey()}/restore")->assertRedirect('/workers?status=archived');

    \expect($worker->refresh()->isArchived())->toBeFalse();
});

\test('worker with an open attendance session is blocked from removal', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'active_worker_id' => $worker->getKey(),
        'ended_at' => null,
        'voided_at' => null,
    ]);

    $this->be($admin, 'users')->delete("/workers/{$worker->getKey()}")->assertRedirect('/workers');

    \expect($worker->refresh()->isArchived())->toBeFalse();
});

\test('worker with a future shift request is blocked from removal', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => CarbonImmutable::today()->addMonth()->toDateString(),
    ]);

    $this->be($admin, 'users')->delete("/workers/{$worker->getKey()}")->assertRedirect('/workers');

    \expect($worker->refresh()->isArchived())->toBeFalse();
});
