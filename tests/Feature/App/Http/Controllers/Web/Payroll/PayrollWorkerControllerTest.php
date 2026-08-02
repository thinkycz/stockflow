<?php

declare(strict_types=1);

use App\Models\PayrollWorkerEntry;
use App\Models\Store;
use App\Models\Worker;

\test('admin can add and remove an empty worker from payroll', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $query = '?store_id=' . $store->getKey();
    $period = ['year' => 2026, 'month' => 7];

    $this->be($admin, 'users')
        ->post('/payroll/workers' . $query, [...$period, 'worker_id' => $worker->getKey()])
        ->assertRedirect();
    \expect(PayrollWorkerEntry::query()->count())->toBe(1);

    $this->be($admin, 'users')
        ->delete('/payroll/workers/' . $worker->getKey() . $query, $period)
        ->assertRedirect('/payroll?store_id=' . $store->getKey() . '&year=2026&month=7');
    \expect(PayrollWorkerEntry::query()->count())->toBe(0);
});
