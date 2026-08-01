<?php

declare(strict_types=1);

use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin sees payroll for the active retail store and limited user is denied', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Retail']);

    $this->be($admin, 'users')
        ->get('/payroll?store_id=' . $store->getKey() . '&year=2026&month=7', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'payroll/Index')
        ->assertJsonPath('props.active_store.name', 'Retail')
        ->assertJsonPath('props.payroll_report.status', 'open');

    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->get('/payroll')->assertRedirect('/dashboard');
});
