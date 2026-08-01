<?php

declare(strict_types=1);

use App\Models\PayrollWageOverride;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;

\test('admin can save and reset a monthly wage override', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-10',
    ]);
    $url = '?store_id=' . $store->getKey();
    $payload = [
        'year' => 2026,
        'month' => 7,
        'worker_id' => $worker->getKey(),
        'hours' => 12.5,
        'hourly_rate' => 150,
    ];

    $this->be($admin, 'users')->put('/payroll/wage-override' . $url, $payload)->assertRedirect();
    $override = PayrollWageOverride::query()->firstOrFail();
    \expect($override->getHours())->toBe(12.5)
        ->and($override->getHourlyRate())->toBe(150.0);

    $this->be($admin, 'users')->delete('/payroll/wage-override' . $url, [
        'year' => 2026,
        'month' => 7,
        'worker_id' => $worker->getKey(),
    ])->assertRedirect();
    \expect(PayrollWageOverride::query()->count())->toBe(0);
});
