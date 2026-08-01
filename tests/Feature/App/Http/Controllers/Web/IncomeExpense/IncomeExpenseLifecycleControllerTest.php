<?php

declare(strict_types=1);

use App\Models\FinancialReport;
use App\Models\Store;

\test('admin can close and reopen a report', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $url = '?store_id=' . $store->getKey();
    $payload = ['year' => 2026, 'month' => 7];

    $this->be($admin, 'users')->post('/income-expenses/close' . $url, $payload)->assertRedirect();
    \expect(FinancialReport::query()->firstOrFail()->isClosed())->toBeTrue();
    $this->be($admin, 'users')->post('/income-expenses/reopen' . $url, $payload)->assertRedirect();
    \expect(FinancialReport::query()->firstOrFail()->isClosed())->toBeFalse();
});

\test('copy previous action is idempotent', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $url = '/income-expenses/copy-previous?store_id=' . $store->getKey();

    $this->be($admin, 'users')->post($url, ['year' => 2026, 'month' => 2])->assertRedirect();
    $this->be($admin, 'users')->post($url, ['year' => 2026, 'month' => 2])->assertRedirect();
});

\test('warehouse reports reject lifecycle mutations', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();

    $this->be($admin, 'users')->post(
        '/income-expenses/close?store_id=' . $warehouse->getKey(),
        ['year' => 2026, 'month' => 7],
    )->assertNotFound();
    \expect(FinancialReport::query()->count())->toBe(0);
});
