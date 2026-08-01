<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;

\test('admin can open one payslip detail in the active store and month', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Retail']);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-10',
    ]);
    $url = '/payroll/workers/' . $worker->getKey() . '?store_id=' . $store->getKey() . '&year=2026&month=7';

    $this->be($admin, 'users')->get($url, $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'payroll/Show')
        ->assertJsonPath('props.active_store.name', 'Retail')
        ->assertJsonPath('props.filters.year', 2026)
        ->assertJsonPath('props.filters.month', 7)
        ->assertJsonPath('props.payslip.worker_id', $worker->getKey());

    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->get($url)->assertRedirect('/dashboard');
});

\test('payslip detail rejects another admin worker and a month without a payslip', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    [$otherAdmin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $otherWorker = Worker::factory()->create(['user_id' => $otherAdmin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-10',
    ]);
    $base = '/payroll/workers/';
    $query = '?store_id=' . $store->getKey() . '&year=2026&month=7';

    $this->be($admin, 'users')->get($base . $otherWorker->getKey() . $query)->assertNotFound();
    $this->be($admin, 'users')->get($base . $worker->getKey() . '?store_id=' . $store->getKey() . '&year=2026&month=8')->assertNotFound();
    $this->be($admin, 'users')->get($base . $worker->getKey() . '?store_id=' . $otherStore->getKey() . '&year=2026&month=7')->assertNotFound();
});
