<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('admin can create a shift', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'hourly_rate' => 200.50,
    ]);

    $response = $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/shifts', [
            'worker_id' => $worker->getKey(),
            'date' => '2026-07-15',
            'start_time' => '10:00',
            'end_time' => '15:00',
        ], $this->inertiaHeaders());

    $response->assertRedirect();
    $shift = Shift::query()->where('worker_id', $worker->getKey())->first();
    \expect($shift)->not->toBeNull();
    \expect($shift->getStoreId())->toBe($store->getKey());
    \expect($shift->getDate())->toBe('2026-07-15');
    \expect($shift->getStartTimeShort())->toBe('10:00');
    \expect($shift->getEndTimeShort())->toBe('15:00');

    $worker->update(['hourly_rate' => 350]);

    \expect($shift->refresh()->getHourlyRate())->toBe(200.50);
});

\test('worker_id is required', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);

    $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/shifts', [
            'worker_id' => '',
            'date' => '2026-07-15',
            'start_time' => '10:00',
            'end_time' => '15:00',
        ], $this->inertiaHeaders())->assertStatus(422);
});

\test('date is required', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/shifts', [
            'worker_id' => $worker->getKey(),
            'date' => '',
            'start_time' => '10:00',
            'end_time' => '15:00',
        ], $this->inertiaHeaders())->assertStatus(422);
});

\test('start_time must be a valid time format', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/shifts', [
            'worker_id' => $worker->getKey(),
            'date' => '2026-07-15',
            'start_time' => 'not-a-time',
            'end_time' => '15:00',
        ], $this->inertiaHeaders())->assertStatus(422);
});

\test('shift times must use quarter-hour increments', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')
        ->post('/shifts', [
            'worker_id' => $worker->getKey(),
            'date' => '2026-07-15',
            'start_time' => '10:07',
            'end_time' => '15:00',
        ], $this->inertiaHeaders())
        ->assertStatus(422);

    $this->assertDatabaseCount('shifts', 0);
});

\test('shift end time must be after its start time', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $response = $this->be($admin, 'users')
        ->post('/shifts', [
            'worker_id' => $worker->getKey(),
            'date' => '2026-07-15',
            'start_time' => '15:00',
            'end_time' => '10:00',
        ], $this->inertiaHeaders());

    $response->assertStatus(422);
    \expect($response->json('props.errors'))
        ->toHaveKey('end_time')
        ->not->toHaveKeys(['worker_id', 'date', 'start_time']);

    $this->assertDatabaseCount('shifts', 0);
});

\test('limited user cannot create shifts', function (): void {
    $admin = Database\Factories\UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $limited = Database\Factories\UserFactory::new()->limited($store)->createOne();

    $this->be($limited, 'users')
        ->withSession(['_token' => 'test'])
        ->withHeaders(['X-CSRF-TOKEN' => 'test'])
        ->post('/shifts', [
            'worker_id' => $worker->getKey(),
            'date' => '2026-07-15',
            'start_time' => '10:00',
            'end_time' => '15:00',
        ], $this->inertiaHeaders())
        ->assertRedirect('/dashboard');

    \expect(Shift::query()->where('worker_id', $worker->getKey())->exists())->toBeFalse();
});
