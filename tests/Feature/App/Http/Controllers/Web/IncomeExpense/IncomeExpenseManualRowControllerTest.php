<?php

declare(strict_types=1);

use App\Models\FinancialReportManualRow;
use App\Models\Store;

\test('admin can create update and delete a manual financial row', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $base = '/income-expenses/manual-rows?store_id=' . $store->getKey();
    $payload = ['year' => 2026, 'month' => 7, 'direction' => 'expense', 'label' => 'Rent', 'occurred_on' => '2026-07-01', 'amount' => 1000, 'note' => null];

    $this->be($admin, 'users')->post($base, $payload)->assertRedirect();
    $row = FinancialReportManualRow::query()->firstOrFail();
    $this->be($admin, 'users')->put('/income-expenses/manual-rows/' . $row->getKey() . '?store_id=' . $store->getKey(), [...$payload, 'amount' => 1200])->assertRedirect();
    \expect($row->refresh()->getAmount())->toBe(1200.0);
    $this->be($admin, 'users')->delete('/income-expenses/manual-rows/' . $row->getKey() . '?store_id=' . $store->getKey(), ['year' => 2026, 'month' => 7])->assertRedirect();
    \expect(FinancialReportManualRow::query()->count())->toBe(0);
});

\test('manual date must be in the selected month', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $this->be($admin, 'users')->post('/income-expenses/manual-rows?store_id=' . $store->getKey(), [
        'year' => 2026, 'month' => 7, 'direction' => 'expense', 'label' => 'Rent', 'occurred_on' => '2026-08-01', 'amount' => 1000,
    ])->assertUnprocessable();
    \expect(FinancialReportManualRow::query()->count())->toBe(0);
});
