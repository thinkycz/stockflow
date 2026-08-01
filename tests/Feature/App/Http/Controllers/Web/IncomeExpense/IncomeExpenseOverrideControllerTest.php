<?php

declare(strict_types=1);

use App\Models\FinancialReportOverride;
use App\Models\Store;
use App\Services\FinancialReportService;
use Database\Factories\UserFactory;

\test('admin can store and remove a calculated row override', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $url = '/income-expenses/overrides?store_id=' . $store->getKey();
    $payload = ['year' => 2026, 'month' => 7, 'source_type' => 'revenue', 'source_key' => 'cash', 'amount' => 500];

    $this->be($admin, 'users')->post($url, $payload)->assertRedirect();
    \expect(FinancialReportOverride::query()->count())->toBe(1);
    $this->be($admin, 'users')->delete($url, $payload)->assertRedirect();
    \expect(FinancialReportOverride::query()->count())->toBe(0);
});

\test('closed reports and limited administrators cannot mutate overrides', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $url = '/income-expenses/overrides?store_id=' . $store->getKey();
    $payload = ['year' => 2026, 'month' => 7, 'source_type' => 'revenue', 'source_key' => 'cash', 'amount' => 500];
    (new FinancialReportService())->close($admin, $store, 2026, 7);

    $this->be($admin, 'users')->post($url, $payload)->assertUnprocessable();

    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->post($url, $payload)->assertRedirect('/dashboard');
    \expect(FinancialReportOverride::query()->count())->toBe(0);
});
