<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('admin can print all payslips or one worker', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Retail']);
    $first = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $second = Worker::factory()->create(['user_id' => $admin->getKey()]);
    foreach ([$first, $second] as $worker) {
        Shift::factory()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $worker->getKey(),
            'date' => '2026-07-10',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'hourly_rate' => 100,
        ]);
    }
    $base = '/payroll/print?store_id=' . $store->getKey() . '&year=2026&month=7';

    $this->be($admin, 'users')->get($base, $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'payroll/Print')
        ->assertJsonCount(2, 'props.payroll_report.payslips');

    $this->be($admin, 'users')->get($base . '&worker_id=' . $first->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.payroll_report.payslips')
        ->assertJsonPath('props.payroll_report.payslips.0.worker_id', $first->getKey());
});
