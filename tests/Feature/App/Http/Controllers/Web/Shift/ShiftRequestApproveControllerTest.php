<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;

\test('admin can approve a shift request', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'hourly_rate' => 200.50,
    ]);
    $shiftRequest = ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-09-15',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    $this->be($admin, 'users')->post(
        "/shift-requests/{$shiftRequest->getKey()}/approve?year=2026&month=9",
        ['start_time' => '09:00', 'end_time' => '17:00'],
        $this->inertiaHeaders(),
    )->assertRedirect('/shifts?month=9&year=2026');

    $this->assertDatabaseMissing('shift_requests', ['id' => $shiftRequest->getKey()]);
    $shift = Shift::query()->where('worker_id', $worker->getKey())->first();
    \expect($shift)->not->toBeNull();
    \expect($shift->getStoreId())->toBe($store->getKey());
    \expect($shift->getDate())->toBe('2026-09-15');
    \expect($shift->getStartTimeShort())->toBe('09:00');
    \expect($shift->getEndTimeShort())->toBe('17:00');
    \expect($shift->getHourlyRate())->toBe(200.50);
});

\test('admin can adjust request times while approving it', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shiftRequest = ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-09-16',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    $this->be($admin, 'users')->post(
        "/shift-requests/{$shiftRequest->getKey()}/approve",
        ['start_time' => '10:15', 'end_time' => '16:45'],
        $this->inertiaHeaders(),
    )->assertRedirect();

    $shift = Shift::query()->where('worker_id', $worker->getKey())->firstOrFail();
    \expect($shift->getDate())->toBe('2026-09-16');
    \expect($shift->getStartTimeShort())->toBe('10:15');
    \expect($shift->getEndTimeShort())->toBe('16:45');
    $this->assertDatabaseMissing('shift_requests', ['id' => $shiftRequest->getKey()]);
});

\test('approving a request matching an existing shift removes the request without a duplicate', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-09-17',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);
    $shiftRequest = ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-09-17',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    $this->be($admin, 'users')->post(
        "/shift-requests/{$shiftRequest->getKey()}/approve",
        ['start_time' => '09:00', 'end_time' => '17:00'],
        $this->inertiaHeaders(),
    )->assertRedirect();

    $this->assertDatabaseCount('shifts', 1);
    $this->assertDatabaseMissing('shift_requests', ['id' => $shiftRequest->getKey()]);
});

\test('approving an overlapping request requires an explicit override and remains atomic', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-09-18',
        'start_time' => '09:00',
        'end_time' => '13:00',
    ]);
    $shiftRequest = ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-09-18',
        'start_time' => '12:00',
        'end_time' => '17:00',
    ]);
    $url = "/shift-requests/{$shiftRequest->getKey()}/approve";
    $payload = ['start_time' => '12:00', 'end_time' => '17:00'];

    $this->be($admin, 'users')->post($url, $payload, $this->inertiaHeaders())
        ->assertRedirect()
        ->assertSessionHasErrors(['overlap' => \__('This shift overlaps an existing assignment.')]);

    $this->assertDatabaseCount('shifts', 1);
    $this->assertDatabaseHas('shift_requests', ['id' => $shiftRequest->getKey()]);

    $this->be($admin, 'users')->post($url, [...$payload, 'allow_overlap' => true], $this->inertiaHeaders())
        ->assertRedirect();

    $this->assertDatabaseCount('shifts', 2);
    $this->assertDatabaseMissing('shift_requests', ['id' => $shiftRequest->getKey()]);
});

\test('invalid approval times leave the request unchanged', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shiftRequest = ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
    ]);

    $this->be($admin, 'users')->post(
        "/shift-requests/{$shiftRequest->getKey()}/approve",
        ['start_time' => '17:00', 'end_time' => '09:00'],
        $this->inertiaHeaders(),
    )->assertRedirect()->assertSessionHasErrors(['end_time']);

    $this->assertDatabaseCount('shifts', 0);
    $this->assertDatabaseHas('shift_requests', ['id' => $shiftRequest->getKey()]);
});

\test('admin cannot approve a request from another active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $activeStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shiftRequest = ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $otherStore->getKey(),
        'worker_id' => $worker->getKey(),
    ]);

    $this->be($admin, 'users')->post(
        "/shift-requests/{$shiftRequest->getKey()}/approve?store_id={$activeStore->getKey()}",
        ['start_time' => '09:00', 'end_time' => '17:00'],
        $this->inertiaHeaders(),
    )->assertNotFound();

    $this->assertDatabaseCount('shifts', 0);
    $this->assertDatabaseHas('shift_requests', ['id' => $shiftRequest->getKey()]);
});

\test('admin receives not found for an unknown shift request', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);

    $this->be($admin, 'users')->post(
        '/shift-requests/999999/approve',
        ['start_time' => '09:00', 'end_time' => '17:00'],
        $this->inertiaHeaders(),
    )->assertNotFound();
});

\test('limited user cannot approve a shift request', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $shiftRequest = ShiftRequest::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
    ]);

    $this->be($limited, 'users')->post(
        "/shift-requests/{$shiftRequest->getKey()}/approve",
        ['start_time' => '09:00', 'end_time' => '17:00'],
        $this->inertiaHeaders(),
    )->assertRedirect('/dashboard');

    $this->assertDatabaseCount('shifts', 0);
    $this->assertDatabaseHas('shift_requests', ['id' => $shiftRequest->getKey()]);
});
