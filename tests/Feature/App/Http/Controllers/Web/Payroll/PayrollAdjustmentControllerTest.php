<?php

declare(strict_types=1);

use App\Models\PayrollAdjustment;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('admin can create update and delete a payroll adjustment', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-10',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'hourly_rate' => 100,
    ]);
    $url = '?store_id=' . $store->getKey();
    $payload = [
        'year' => 2026,
        'month' => 7,
        'worker_id' => $worker->getKey(),
        'type' => 'tip',
        'amount' => 25,
        'reason' => 'Shared tips',
    ];

    $this->be($admin, 'users')->post('/payroll/adjustments' . $url, $payload)->assertRedirect();
    $adjustment = PayrollAdjustment::query()->firstOrFail();
    \expect($adjustment->getAmount())->toBe(25.0);

    $this->be($admin, 'users')->put('/payroll/adjustments/' . $adjustment->getKey() . $url, [
        ...$payload,
        'amount' => 30,
        'reason' => 'Corrected tips',
    ])->assertRedirect();
    \expect($adjustment->refresh()->getAmount())->toBe(30.0);

    $this->be($admin, 'users')->delete('/payroll/adjustments/' . $adjustment->getKey() . $url, [
        'year' => 2026,
        'month' => 7,
    ])->assertRedirect();
    \expect(PayrollAdjustment::query()->count())->toBe(0);
});

\test('archived worker cannot receive a new payroll adjustment', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'archived_at' => \now()]);

    $this->be($admin, 'users')->post('/payroll/adjustments?store_id=' . $store->getKey(), [
        'year' => 2026,
        'month' => 7,
        'worker_id' => $worker->getKey(),
        'type' => 'tip',
        'amount' => 25,
        'reason' => 'Shared tips',
    ])->assertNotFound();

    \expect(PayrollAdjustment::query()->count())->toBe(0);
});
