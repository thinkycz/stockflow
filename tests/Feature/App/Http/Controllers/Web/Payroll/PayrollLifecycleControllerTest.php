<?php

declare(strict_types=1);

use App\Models\PayrollReport;
use App\Models\Store;

\test('admin can close and reopen a payroll report', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $url = '?store_id=' . $store->getKey();
    $payload = ['year' => 2026, 'month' => 7];

    $this->be($admin, 'users')->post('/payroll/close' . $url, $payload)->assertRedirect();
    \expect(PayrollReport::query()->firstOrFail()->isClosed())->toBeTrue();

    $this->be($admin, 'users')->post('/payroll/reopen' . $url, $payload)->assertRedirect();
    \expect(PayrollReport::query()->firstOrFail()->isClosed())->toBeFalse();
});
