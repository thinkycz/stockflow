<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementReconciliationService;
use App\Domain\Finance\FinancialReportReadService;
use App\Domain\Finance\FinancialReportService;
use App\Domain\Statements\StatementService;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\FinancialReport;
use App\Models\FinancialReportOverride;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;

\test('all live surfaces share exact commission VAT without modifying confirmed periods', function (string $channel, string $cash, string $transfer, string $net, string $deduction): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create([
        'date' => '2026-08-01', 'card' => '0', 'wolt' => '0', 'bolt' => '0', 'foodora' => '0', $channel => '1000', 'bolt_cash' => $cash,
    ]);
    $bank = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed']);
    $transaction = BankStatementTransaction::factory()->forStatement($bank)->create([
        'category' => $channel, 'amount' => $transfer, 'booked_on' => '2026-08-07',
        'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01', 'manually_edited' => true, 'review_note' => 'Reviewed period',
    ]);
    $original = $transaction->fresh()->getAttributes();
    $reconciliation = new BankStatementReconciliationService();
    $check = $reconciliation->forTransaction($transaction);
    $metrics = (new StatementService())->buildMetrics($statement, [$day], 0.0);
    $summary = (new StatementService())->buildReport($admin, $store->getKey(), 2026, 8);
    $income = \collect((new FinancialReportReadService())->build($admin, $store, 2026, 8)['income_rows'])->firstWhere('source_key', $channel);
    \expect($check)->toMatchArray(['status' => 'matched', 'expected' => $transfer, 'difference' => '0.00'])
        ->and($check['fees']['deduction'])->toBe($deduction)
        ->and($metrics['marketplace_fees'][$channel])->toBe($check['fees'])
        ->and($summary['totals']['marketplace_fees'][$channel])->toBe($check['fees'])
        ->and($income['details']['marketplace_fees'])->toBe($check['fees'])
        ->and($income['calculated_amount'])->toBe((float) $net)
        ->and($reconciliation->monthlyStatus($admin, $store, 2026, 8)['cells']['2026-08-01'][$channel][0]['state'])->toBe('verified')
        ->and($transaction->fresh()->getAttributes())->toBe($original)
        ->and($bank->fresh()->getStatus()->value)->toBe('confirmed');
    // A pre-VAT synthetic payment stays a visible discrepancy instead of becoming a false match.
    $transaction->update(['amount' => $channel === 'bolt' ? '580.00' : '700.00']);
    \expect($reconciliation->monthlyStatus($admin, $store, 2026, 8)['cells']['2026-08-01'][$channel][0]['state'])->toBe('review');
})->with([
    ['wolt', '0', '637.00', '637.00', '363.00'],
    ['foodora', '0', '637.00', '637.00', '363.00'],
    ['bolt', '200', '491.80', '691.80', '508.20'],
]);

\test('legacy closed snapshots and manual overrides survive the calculation correction', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'wolt' => '1000']);
    $legacy = ['income_rows' => [['source_key' => 'wolt', 'calculated_amount' => 700]], 'expense_rows' => [], 'totals' => ['income' => 700, 'expenses' => 0, 'profit' => 700]];
    $report = FinancialReport::factory()->forStore($store)->forMonth(2026, 8)->create(['status' => 'closed', 'snapshot' => $legacy]);
    FinancialReportOverride::factory()->create(['financial_report_id' => $report->getKey(), 'source_type' => 'revenue', 'source_key' => 'wolt', 'amount' => '600']);
    $read = new FinancialReportReadService();
    \expect($read->build($admin, $store, 2026, 8)['income_rows'])->toBe($legacy['income_rows']);
    (new FinancialReportService())->reopen($admin, $store, 2026, 8);
    $wolt = \collect($read->build($admin, $store, 2026, 8)['income_rows'])->firstWhere('source_key', 'wolt');
    \expect($wolt)->toMatchArray(['calculated_amount' => 637.0, 'effective_amount' => 600.0, 'override_amount' => 600.0]);
});

\test('nonpositive estimates never match a small incoming payment or auto select a period', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'bolt' => '0', 'bolt_cash' => '1']);
    $bank = BankStatement::factory()->forStore($store)->create();
    $transaction = BankStatementTransaction::factory()->forStatement($bank)->create(['category' => 'bolt', 'amount' => '1.00', 'booked_on' => '2026-08-04', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01']);
    $service = new BankStatementReconciliationService();
    \expect($service->forTransaction($transaction))->toMatchArray(['status' => 'mismatch', 'expected' => '-0.42']);
    $transaction->update(['sales_from' => null, 'sales_to' => null, 'description' => 'Settlement period: 1.8.2026 - 1.8.2026']);
    \expect($service->forStatement($bank)['rows'][0]['automatic'])->toBeNull();
});

\test('month commissions are rounded after summing days rather than adding daily fee estimates', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    foreach (['2026-08-01', '2026-08-02'] as $date) {
        StatementDay::factory()->for($statement, 'statement')->create(['date' => $date, 'wolt' => '0.01']);
    }
    $fees = (new StatementService())->buildReport($admin, $store->getKey(), 2026, 8)['totals']['marketplace_fees']['wolt'];
    \expect($fees)->toMatchArray(['base' => '0.02', 'commission' => '0.01', 'vat' => '0.00', 'expected_transfer' => '0.01']);
});
