<?php

declare(strict_types=1);

use App\Enums\BankStatementStatusEnum;
use App\Enums\BankStatementTransactionCategoryEnum;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Services\BankStatementReconciliationService;

\test('card payouts reconcile against current net statement revenue with a five crown tolerance', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $dailyStatement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    StatementDay::factory()->for($dailyStatement, 'statement')->create([
        'date' => '2026-08-01',
        'card' => '1000.00',
        'total' => '1000.00',
    ]);
    $bankStatement = BankStatement::factory()->forStore($store)->create();
    $transaction = BankStatementTransaction::factory()->forStatement($bankStatement)->create([
        'amount' => '985.00',
        'category' => BankStatementTransactionCategoryEnum::CARD->value,
        'sales_from' => '2026-08-01',
        'sales_to' => '2026-08-01',
    ]);

    $result = (new BankStatementReconciliationService())->forTransaction($transaction);
    \expect($result)->toMatchArray([
        'status' => 'matched',
        'actual' => '985.00',
        'expected' => '990.00',
        'difference' => '-5.00',
    ]);

    $transaction->update(['amount' => '984.99']);

    \expect((new BankStatementReconciliationService())->forTransaction($transaction)['status'])
        ->toBe('mismatch');
});

\test('period-less active imports are not attributed to every report month', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::QUEUED->value,
        'period_from' => null,
        'period_to' => null,
    ]);

    \expect((new BankStatementReconciliationService())->monthlyStatus($user, $store, 2026, 8))
        ->toMatchArray(['statement_id' => null, 'status' => 'not_uploaded']);
});

\test('marketplace formulas include bolt cash and excluded movements never affect sales checks', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $dailyStatement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    StatementDay::factory()->for($dailyStatement, 'statement')->create([
        'date' => '2026-08-01',
        'wolt' => '100.00',
        'bolt' => '100.00',
        'bolt_cash' => '40.00',
        'foodora' => '200.00',
        'total' => '440.00',
    ]);
    $bankStatement = BankStatement::factory()->forStore($store)->create();
    $service = new BankStatementReconciliationService();

    foreach ([
        BankStatementTransactionCategoryEnum::WOLT->value => '70.00',
        BankStatementTransactionCategoryEnum::FOODORA->value => '140.00',
        BankStatementTransactionCategoryEnum::BOLT->value => '51.00',
    ] as $category => $amount) {
        $transaction = BankStatementTransaction::factory()->forStatement($bankStatement)->create([
            'amount' => $amount,
            'category' => $category,
            'sales_from' => '2026-08-01',
            'sales_to' => '2026-08-01',
        ]);

        \expect($service->forTransaction($transaction)['status'])->toBe('matched');
    }

    $outgoing = BankStatementTransaction::factory()->forStatement($bankStatement)->create([
        'amount' => '-500.00',
        'category' => BankStatementTransactionCategoryEnum::OUTGOING->value,
        'sales_from' => null,
        'sales_to' => null,
    ]);

    \expect($service->forTransaction($outgoing))->toMatchArray([
        'status' => 'excluded',
        'expected' => null,
        'difference' => null,
    ]);
});

\test('a period spanning months uses all current days and missing days remain unresolved', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $august = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    $september = Statement::factory()->forStore($store)->forMonth(2026, 9)->create();
    StatementDay::factory()->for($august, 'statement')->create(['date' => '2026-08-31', 'wolt' => '100.00', 'total' => '100.00']);
    StatementDay::factory()->for($september, 'statement')->create(['date' => '2026-09-01', 'wolt' => '100.00', 'total' => '100.00']);
    $bankStatement = BankStatement::factory()->forStore($store)->create();
    $transaction = BankStatementTransaction::factory()->forStatement($bankStatement)->create([
        'amount' => '140.00',
        'category' => BankStatementTransactionCategoryEnum::WOLT->value,
        'sales_from' => '2026-08-31',
        'sales_to' => '2026-09-01',
    ]);
    $service = new BankStatementReconciliationService();

    \expect($service->forTransaction($transaction)['status'])->toBe('matched');

    StatementDay::query()->whereDate('date', '2026-09-01')->delete();

    \expect($service->forTransaction($transaction))->toMatchArray([
        'status' => 'unresolved',
        'reason' => 'missing_statement_days',
    ]);
});
