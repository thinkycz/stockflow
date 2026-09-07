<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementReconciliationService;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Support\MarketplacePayout;

\test('Wolt boundaries use separate tolerances and never verify a receipt', function (string $amount, string $status): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'wolt' => '1000']);
    $bank = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed']);
    $transaction = BankStatementTransaction::factory()->forStatement($bank)->create([
        'category' => 'wolt', 'amount' => $amount, 'booked_on' => '2026-08-07', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01',
    ]);
    $service = new BankStatementReconciliationService();
    $check = $service->forTransaction($transaction);
    \expect($check)->toMatchArray(['pairing' => 'paired', 'status' => $status, 'amount_check' => $status])
        ->and($check['range'])->toMatchArray(['tolerance_min' => '7.06', 'tolerance_max' => '7.81'])
        ->and($service->monthlyStatus($admin, $store, 2026, 8)['cells']['2026-08-01']['wolt'][0]['state'])->toBe('review');
})->with([
    ['557.34', 'within_estimate'], ['557.33', 'outside_estimate'],
    ['632.71', 'within_estimate'], ['632.72', 'outside_estimate'], ['600.00', 'within_estimate'],
]);

\test('dated Bolt aggregation separates regimes and leaves the documented seven crown difference', function (): void {
    $days = [];
    foreach (['2026-08-02', '2026-08-03'] as $date) {
        $day = new StatementDay();
        $day->forceFill(['date' => $date, 'wolt' => '0', 'foodora' => '0', 'bolt' => '1000', 'bolt_cash' => '200']);
        $days[] = $day;
    }
    $fees = MarketplacePayout::forDays($days)['bolt'];
    \expect($fees)->toMatchArray(['commission' => '840.00', 'vat' => '88.20', 'expected_transfer' => '1071.80', 'net_revenue' => '1471.80'])
        ->and($fees['segments'])->toHaveCount(2);
    \expect(MarketplacePayout::calculate('bolt', '3280', '2880', '2026-08-02')['expected_transfer'])->toBe('1124.00');
});

\test('documented Wolt payments never become verified matches', function (string $gross, string $actual, string $status): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'wolt' => $gross]);
    $bank = BankStatement::factory()->forStore($store)->create();
    $transaction = BankStatementTransaction::factory()->forStatement($bank)->create([
        'category' => 'wolt', 'amount' => $actual, 'booked_on' => '2026-08-07', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01',
    ]);
    \expect((new BankStatementReconciliationService())->forTransaction($transaction)['status'])->toBe($status);
})->with([['7015', '3930.64', 'within_estimate'], ['6250', '2837.69', 'outside_estimate']]);
