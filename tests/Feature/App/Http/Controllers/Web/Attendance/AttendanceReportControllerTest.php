<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\Worker;

\test('administrator can open the attendance report for the active retail store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'calendar_color' => '#12ABEF',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'started_at' => '2026-07-10 08:00:00',
        'ended_at' => '2026-07-10 16:00:00',
    ]);

    $this->be($admin, 'users')->get('/attendance/report?month=2026-07', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'attendance/Report')
        ->assertJsonPath('props.store.id', $store->getKey())
        ->assertJsonPath('props.workers.0.id', $worker->getKey())
        ->assertJsonPath('props.report.rows.0.worker_color', '#12ABEF')
        ->assertJsonPath('props.report.month', '2026-07');
});
