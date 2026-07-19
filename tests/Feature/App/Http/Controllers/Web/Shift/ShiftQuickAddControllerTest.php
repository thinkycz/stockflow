<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;

\test('admin can quick add a preset shift', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'hourly_rate' => 200.50]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'name' => 'Morning',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);

    $response = $this->be($admin, 'users')->postJson('/shifts/quick-add', [
        'worker_id' => $worker->getKey(),
        'shift_preset_id' => $preset->getKey(),
        'date' => '2026-07-15',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'created')
        ->assertJsonPath('shift.worker_id', $worker->getKey())
        ->assertJsonPath('shift.date', '2026-07-15')
        ->assertJsonPath('shift.start_time', '10:00')
        ->assertJsonPath('shift.end_time', '15:00')
        ->assertJsonPath('contribution.minutes', 300)
        ->assertJsonPath('contribution.salary', 1002.5);

    $shift = Shift::query()->first();
    \expect($shift)->not->toBeNull()
        ->and($shift->getStoreId())->toBe($store->getKey())
        ->and($shift->getHourlyRate())->toBe(200.50);
});

\test('repeating an exact quick assignment is idempotent', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);

    $this->be($admin, 'users')->postJson('/shifts/quick-add', [
        'worker_id' => $worker->getKey(),
        'shift_preset_id' => $preset->getKey(),
        'date' => '2026-07-15',
    ])->assertOk()
        ->assertJsonPath('status', 'exists')
        ->assertJsonPath('shift.id', $shift->getKey());

    \expect(Shift::query()->count())->toBe(1);
});

\test('quick add reports overlap and accepts an explicit override', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'start_time' => '14:00',
        'end_time' => '18:00',
    ]);
    $existing = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);

    $payload = [
        'worker_id' => $worker->getKey(),
        'shift_preset_id' => $preset->getKey(),
        'date' => '2026-07-15',
    ];

    $this->be($admin, 'users')->postJson('/shifts/quick-add', $payload)
        ->assertConflict()
        ->assertJsonPath('status', 'overlap')
        ->assertJsonPath('conflicts.0.id', $existing->getKey())
        ->assertJsonPath('conflicts.0.start_time', '10:00')
        ->assertJsonPath('conflicts.0.end_time', '15:00');

    $this->be($admin, 'users')->postJson('/shifts/quick-add', [...$payload, 'allow_overlap' => true])
        ->assertCreated()
        ->assertJsonPath('status', 'created');

    \expect(Shift::query()->count())->toBe(2);
});

\test('adjacent and other employee shifts do not conflict with quick add', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $otherWorker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'start_time' => '15:00',
        'end_time' => '21:00',
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $otherWorker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '15:00',
        'end_time' => '21:00',
    ]);

    $this->be($admin, 'users')->postJson('/shifts/quick-add', [
        'worker_id' => $worker->getKey(),
        'shift_preset_id' => $preset->getKey(),
        'date' => '2026-07-15',
    ])->assertCreated();
});

\test('quick add rejects a preset from another active store and limited users', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $activeStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $otherStore->getKey(),
    ]);

    $this->be($admin, 'users')->postJson("/shifts/quick-add?store_id={$activeStore->getKey()}", [
        'worker_id' => $worker->getKey(),
        'shift_preset_id' => $preset->getKey(),
        'date' => '2026-07-15',
    ])->assertStatus(422);

    $limited = UserFactory::new()->limited($activeStore)->createOne();
    $this->be($limited, 'users')->postJson('/shifts/quick-add', [
        'worker_id' => $worker->getKey(),
        'shift_preset_id' => $preset->getKey(),
        'date' => '2026-07-15',
    ])->assertRedirect('/dashboard');
});

\test('quick add rejects a foreign worker and returns not found without a store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $foreignAdmin = UserFactory::new()->admin()->createOne();
    $foreignWorker = Worker::factory()->create(['user_id' => $foreignAdmin->getKey()]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
    ]);

    $this->be($admin, 'users')->postJson('/shifts/quick-add', [
        'worker_id' => $foreignWorker->getKey(),
        'shift_preset_id' => $preset->getKey(),
        'date' => '2026-07-15',
    ])->assertStatus(422);

    $adminWithoutStore = UserFactory::new()->admin()->createOne();
    $this->be($adminWithoutStore, 'users')->postJson('/shifts/quick-add', [
        'worker_id' => $foreignWorker->getKey(),
        'shift_preset_id' => $preset->getKey(),
        'date' => '2026-07-15',
    ])->assertNotFound();
});
