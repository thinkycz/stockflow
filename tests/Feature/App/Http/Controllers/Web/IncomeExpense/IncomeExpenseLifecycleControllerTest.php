<?php

declare(strict_types=1);

use App\Models\FinancialReport;
use App\Models\Store;
use App\Services\PayrollReportService;

\test('admin can close and reopen a report', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $url = '?store_id=' . $store->getKey();
    $payload = ['year' => 2026, 'month' => 7];
    (new PayrollReportService())->close($admin, $store, 2026, 7);

    $this->be($admin, 'users')->post('/income-expenses/close' . $url, $payload)->assertRedirect();
    \expect(FinancialReport::query()->firstOrFail()->isClosed())->toBeTrue();
    $this->be($admin, 'users')->post('/income-expenses/reopen' . $url, $payload)->assertRedirect();
    \expect(FinancialReport::query()->firstOrFail()->isClosed())->toBeFalse();
});

\test('failed close returns to the report with errors and keeps the admin authenticated', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $index = '/income-expenses?store_id=' . $store->getKey() . '&year=2026&month=7';

    $response = $this->be($admin, 'users')
        ->from($index)
        ->post(
            '/income-expenses/close?store_id=' . $store->getKey(),
            ['year' => 2026, 'month' => 7],
            [
                ...$this->inertiaHeaders(),
                'X-StockFlow-Action' => 'true',
            ],
        );

    $response->assertRedirect($index)->assertSessionHasErrors('report');
    $this->get($index, $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'income-expenses/Index')
        ->assertJsonPath('props.auth.user.id', $admin->getKey())
        ->assertJsonPath('props.flash.error', \__('Close the payroll report before closing the financial report.'))
        ->assertJsonPath('props.errors.report', \__('Close the payroll report before closing the financial report.'));
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
