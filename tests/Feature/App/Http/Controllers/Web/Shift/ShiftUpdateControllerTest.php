<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('admin can update a shift', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'hourly_rate' => 200.50,
    ]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'start_time' => '10:00',
        'end_time' => '15:00',
        'hourly_rate' => 200.50,
    ]);

    $worker->update(['hourly_rate' => 350]);

    $this->be($admin, 'users')
        ->put("/shifts/{$shift->getKey()}", [
            'worker_id' => $worker->getKey(),
            'date' => $shift->getDate(),
            'start_time' => '12:00',
            'end_time' => '20:00',
        ])
        ->assertRedirect();

    $shift->refresh();
    \expect($shift->getStartTimeShort())->toBe('12:00');
    \expect($shift->getEndTimeShort())->toBe('20:00');
    \expect($shift->getHourlyRate())->toBe(200.50);
});

\test('changing the assigned worker snapshots the new worker rate', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $originalWorker = Worker::factory()->create(['user_id' => $admin->getKey(), 'hourly_rate' => 200.50]);
    $replacementWorker = Worker::factory()->create(['user_id' => $admin->getKey(), 'hourly_rate' => 350]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $originalWorker->getKey(),
        'hourly_rate' => 200.50,
    ]);

    $this->be($admin, 'users')
        ->put("/shifts/{$shift->getKey()}", [
            'worker_id' => $replacementWorker->getKey(),
            'date' => $shift->getDate(),
            'start_time' => $shift->getStartTimeShort(),
            'end_time' => $shift->getEndTimeShort(),
        ])
        ->assertRedirect();

    $shift->refresh();
    \expect($shift->getWorkerId())->toBe($replacementWorker->getKey());
    \expect($shift->getHourlyRate())->toBe(350.0);
});

\test('cannot update a shift belonging to another admin', function (): void {
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
        ->put("/shifts/{$foreign->getKey()}", [
            'worker_id' => $worker->getKey(),
            'date' => $foreign->getDate(),
            'start_time' => '10:00',
            'end_time' => '15:00',
        ])
        ->assertNotFound();
});

\test('updating into an overlap requires an explicit override', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '15:00',
        'end_time' => '21:00',
    ]);
    $payload = [
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '14:00',
        'end_time' => '18:00',
    ];

    $this->be($admin, 'users')->put("/shifts/{$shift->getKey()}", $payload, $this->inertiaHeaders())
        ->assertStatus(422)
        ->assertJsonPath('props.errors.overlap.0', \__('This shift overlaps an existing assignment.'));
    \expect($shift->refresh()->getStartTimeShort())->toBe('15:00');

    $this->be($admin, 'users')->put("/shifts/{$shift->getKey()}", [...$payload, 'allow_overlap' => true], $this->inertiaHeaders())
        ->assertRedirect();
    \expect($shift->refresh()->getStartTimeShort())->toBe('14:00');
});
