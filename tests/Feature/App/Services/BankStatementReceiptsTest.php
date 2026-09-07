<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementReconciliationService;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

\test('receipt projection combines confirmed imports by sales month and recalculates all covered days', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    foreach (['2026-07-31', '2026-08-01', '2026-08-02'] as $date) {
        StatementDay::factory()->for($statement, 'statement')->create(['date' => $date, 'wolt' => '100.00', 'bolt' => '100.00', 'bolt_cash' => '40.00']);
    }
    $bank = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed', 'period_from' => '2026-09-01', 'period_to' => '2026-09-30']);
    BankStatementTransaction::factory()->forStatement($bank)->create(['category' => 'wolt', 'amount' => '127.40', 'sales_from' => '2026-07-31', 'sales_to' => '2026-08-01']);
    $second = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed']);
    BankStatementTransaction::factory()->forStatement($second)->create(['category' => 'bolt', 'amount' => '40.71', 'sales_from' => '2026-08-02', 'sales_to' => '2026-08-02']);
    $draft = BankStatement::factory()->forStore($store)->create();
    BankStatementTransaction::factory()->forStatement($draft)->create(['category' => 'wolt', 'amount' => '127.40', 'sales_from' => '2026-07-31', 'sales_to' => '2026-08-01']);
    $service = new BankStatementReconciliationService();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $result = $service->monthlyStatus($user, $store, 2026, 8);
    $queries = \count(DB::getQueryLog());
    DB::disableQueryLog();
    \expect($queries)->toBeLessThanOrEqual(4)
        ->and($result['counts']['matched'])->toBe(2)
        ->and($result['cells']['2026-08-01']['wolt'][0]['state'])->toBe('verified')
        ->and($result['cells']['2026-08-02']['bolt'][0]['check']['expected'])->toBe('40.71')
        ->and($result['cells']['2026-08-02'])->not->toHaveKey('bolt_cash')
        ->and($result['cells'])->not->toHaveKey('2026-07-31');
    StatementDay::query()->where('statement_id', $statement->getKey())->whereDate('date', '2026-07-31')->update(['wolt' => '200.00']);
    \expect($service->monthlyStatus($user, $store, 2026, 8)['cells']['2026-08-01']['wolt'][0]['state'])->toBe('review');
    $second->update(['status' => 'review']);
    \expect($service->monthlyStatus($user, $store, 2026, 8)['cells'])->not->toHaveKey('2026-08-02');
});

\test('overlapping receipts and incomplete periods never receive green marks', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->create();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'card' => '1000.00']);
    $bank = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed']);
    foreach (['2026-08-01', '2026-08-01', '2026-08-03'] as $end) {
        BankStatementTransaction::factory()->forStatement($bank)->create(['category' => 'card', 'amount' => '990.00', 'sales_from' => '2026-08-01', 'sales_to' => $end]);
    }
    $service = new BankStatementReconciliationService();
    $result = $service->monthlyStatus($user, $store, 2026, 8);
    \expect($result['counts']['matched'])->toBe(0)->and($result['counts']['unresolved'])->toBe(3)
        ->and($result['cells']['2026-08-03']['card'][0]['state'])->toBe('review');
    foreach ($result['cells']['2026-08-01']['card'] as $receipt) {
        \expect($receipt['state'])->toBe('review')->and($receipt['check']['reason'])->toBe('period_conflict');
    }
    \expect($service->forStatement($bank)['counts'])->toBe($result['counts']);
});

\test('receipt projection isolates owner store and incoming sales channels', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $user->getKey()]);
    $bank = BankStatement::factory()->forStore($otherStore)->create(['status' => 'confirmed']);
    BankStatementTransaction::factory()->forStatement($bank)->create(['category' => 'wolt', 'amount' => '63.70', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01']);
    $foreign = BankStatement::factory()->create(['store_id' => $store->getKey(), 'status' => 'confirmed']);
    BankStatementTransaction::factory()->forStatement($foreign)->create(['category' => 'wolt', 'amount' => '63.70', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01']);
    $own = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed']);
    BankStatementTransaction::factory()->forStatement($own)->create(['category' => 'card', 'amount' => '-10.00', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01']);
    \expect((new BankStatementReconciliationService())->monthlyStatus($user, $store, 2026, 8)['cells'])->toBe([]);
});
