<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use App\Models\Store;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;

\test('admin can delete a request in a current locked month including archived workers', function (bool $archived): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'archived_at' => $archived ? CarbonImmutable::now() : null]);
    $date = CarbonImmutable::today('Europe/Prague');
    ShiftRequestMonthLock::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(),
        'year' => $date->year, 'month' => $date->month, 'locked_by_user_id' => $admin->getKey(),
    ]);
    $attributes = ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(), 'date' => $date->toDateString()];
    $shiftRequest = ShiftRequest::factory()->create($attributes);
    $shift = Shift::factory()->create($attributes);
    $response = $this->be($admin, 'users')->delete(
        "/shift-requests/{$shiftRequest->getKey()}?store_id={$store->getKey()}&year={$date->year}&month={$date->month}",
        [],
        $this->inertiaHeaders(),
    );
    $response->assertRedirect('/shifts?store_id=' . $store->getKey() . '&month=' . $date->month . '&year=' . $date->year)->assertSessionHasNoErrors();
    \assertInertiaFlash($response, 'success', \__('Shift request deleted.'));
    $this->assertDatabaseMissing('shift_requests', ['id' => $shiftRequest->getKey()]);
    $this->assertDatabaseHas('shifts', ['id' => $shift->getKey()]);
    $this->assertDatabaseCount('shift_request_month_locks', 1);
})->with([false, true]);

\test('request deletion cannot cross the selected store or company and rejects limited users', function (string $case): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shiftRequest = ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
    ]);
    $actor = $admin;
    $selectedStore = $store;
    if ($case === 'store') {
        $selectedStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    } elseif ($case === 'company') {
        [$actor] = \createIsolatedUserWithWarehouse();
        $selectedStore = Store::factory()->create(['user_id' => $actor->getKey(), 'is_warehouse' => false]);
    } elseif ($case === 'limited') {
        $actor = UserFactory::new()->limited($store)->createOne();
    } elseif ($case === 'inactive') {
        $store->update(['status' => 'inactive']);
    }
    $response = $this->be($actor, 'users')->delete(
        '/shift-requests/' . ($case === 'missing' ? 999999 : $shiftRequest->getKey()) . '?store_id=' . $selectedStore->getKey(),
        [],
        $this->inertiaHeaders(),
    );
    if ($case === 'limited') { $response->assertRedirect('/dashboard'); } else { $response->assertNotFound(); }
    $this->assertDatabaseHas('shift_requests', ['id' => $shiftRequest->getKey()]);
})->with(['store', 'company', 'limited', 'inactive', 'missing']);
