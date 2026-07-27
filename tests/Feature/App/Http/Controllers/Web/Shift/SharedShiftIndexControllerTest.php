<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('guest can view the shared store shift calendar with worker names', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
        'shift_share_token' => 'shared-calendar-token',
    ]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Jan',
        'last_name' => 'Novak',
    ]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '09:00',
        'end_time' => '16:00',
    ]);

    $response = $this->get('/public/shifts/shared-calendar-token?year=2026&month=7', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'public-shifts/Index');
    $response->assertJsonPath('props.store.name', $store->getName());
    $response->assertJsonPath('props.shifts.0.worker_name', 'Jan Novak');
    $response->assertJsonPath('props.shifts.0.worker_color', $worker->getCalendarColor());
    $response->assertJsonPath('props.shifts.0.date', '2026-07-15');
    $response->assertJsonPath('props.shifts.0.start_time', '09:00');
    $response->assertJsonPath('props.shifts.0.end_time', '16:00');
});

\test('shared calendar excludes shifts from another store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $sharedStore = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
        'shift_share_token' => 'shared-calendar-token',
    ]);
    $otherStore = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $sharedStore->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $otherStore->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-16',
    ]);

    $response = $this->get('/public/shifts/shared-calendar-token?year=2026&month=7', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonCount(1, 'props.shifts');
    $response->assertJsonPath('props.shifts.0.date', '2026-07-15');
});

\test('unknown public shift calendar token returns not found', function (): void {
    $this->get('/public/shifts/unknown-token', $this->inertiaHeaders())->assertNotFound();
});
