<?php

declare(strict_types=1);

use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin sees a monthly report for the active retail store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Retail']);

    $this->be($admin, 'users')->get('/income-expenses?store_id=' . $store->getKey() . '&year=2026&month=7', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'income-expenses/Index')
        ->assertJsonPath('props.active_store.name', 'Retail')
        ->assertJsonPath('props.financial_report.report.status', 'open');
});

\test('warehouse renders an empty financial state and limited user is denied', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $this->be($admin, 'users')->get('/income-expenses?store_id=' . $warehouse->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.financial_report', null);

    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->get('/income-expenses')->assertRedirect('/dashboard');
});
