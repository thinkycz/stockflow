<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\Store;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;

\test('admin sees a monthly report for the active retail store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Retail']);

    $this->be($admin, 'users')->get('/income-expenses?store_id=' . $store->getKey() . '&year=2026&month=7', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'income-expenses/Index')
        ->assertJsonPath('props.active_store.name', 'Retail')
        ->assertJsonPath('props.financial_report.report.status', 'open');
});

\test('inactive store financial history remains directly readable but rejects mutations', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $active = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Active retail']);
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'name' => 'Historical retail',
        'status' => StoreStatusEnum::INACTIVE->value,
    ]);

    $this->be($admin, 'users')
        ->get('/income-expenses?store_id=' . $store->getKey() . '&year=2026&month=7', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.active_store.id', $store->getKey())
        ->assertJsonPath('props.active_store.is_active', false);

    $this->post('/income-expenses/manual-rows', [
        'store_id' => $store->getKey(),
        'year' => 2026,
        'month' => 7,
        'direction' => 'expense',
        'label' => 'Late edit',
        'occurred_on' => '2026-07-01',
        'amount' => 1,
    ])->assertNotFound();

    \expect(DB::table('financial_report_manual_rows')->count())->toBe(0)
        ->and(DB::table('financial_reports')->where('store_id', $active->getKey())->count())->toBe(0);
});

\test('warehouse renders an empty financial state and limited user is denied', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $this->be($admin, 'users')->get('/income-expenses?store_id=' . $warehouse->getKey(), $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.financial_report', null);

    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->get('/income-expenses')->assertRedirect('/dashboard');
});
