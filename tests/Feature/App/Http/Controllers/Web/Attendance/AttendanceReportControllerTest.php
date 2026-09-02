<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;

\test('administrator can open the attendance report for the active retail store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Active',
        'last_name' => 'Aaron',
        'calendar_color' => '#12ABEF',
    ]);
    $archived = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Archived',
        'last_name' => 'Zed',
        'archived_at' => \now(),
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
        ->assertJsonPath('props.workers.1.id', $archived->getKey())
        ->assertJsonCount(1, 'props.active_workers')
        ->assertJsonPath('props.active_workers.0.id', $worker->getKey())
        ->assertJsonPath('props.report.rows.0.worker_color', '#12ABEF')
        ->assertJsonPath('props.report.month', '2026-07');
});

\test('inactive attendance history is read only and cannot fall back to an active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $active = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Active retail']);
    $inactive = Store::factory()->inactive()->create(['user_id' => $admin->getKey(), 'name' => 'Historical retail']);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($admin, 'users')
        ->get('/attendance/report?store_id=' . $inactive->getKey() . '&month=2026-07', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.store.id', $inactive->getKey())
        ->assertJsonPath('props.store.is_active', false);

    $this->be($admin, 'users')->post('/attendance/corrections', [
        'store_id' => $inactive->getKey(),
        'worker_id' => $worker->getKey(),
        'started_at' => '2026-07-20T08:00',
        'ended_at' => '2026-07-20T16:00',
        'breaks' => [],
        'reason' => 'Late correction',
    ])->assertNotFound();

    \expect(AttendanceSession::query()->count())->toBe(0)
        ->and(DB::table('attendance_sessions')->where('store_id', $active->getKey())->count())->toBe(0);
});
