<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementReconciliationService;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * @return array{BankStatement, Statement}
 */
function payoutFixture(): array
{
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    return [BankStatement::factory()->forStore($store)->create(), Statement::factory()->forStore($store)->forMonth(2026, 8)->create()];
}

/**
 * @param array<string, mixed> $attributes
 */
function missingPayout(BankStatement $bank, array $attributes = []): BankStatementTransaction
{
    return BankStatementTransaction::factory()->forStatement($bank)->create([
        'category' => 'wolt', 'amount' => '63.70', 'booked_on' => '2026-08-15',
        'sales_from' => null, 'sales_to' => null, 'description' => null, 'specific_symbol' => null,
        ...$attributes,
    ]);
}

\test('tolerance boundaries preserve pairing and exact signed differences', function (string $category, string $gross, string $expected, string $tolerance): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', $category => $gross, 'bolt_cash' => '0.00']);
    $transaction = \missingPayout($bank, ['category' => $category, 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01']);
    $service = new BankStatementReconciliationService();
    foreach ([-1, 1] as $sign) {
        $amount = BigDecimal::of($expected)->plus(BigDecimal::of($tolerance)->multipliedBy($sign));
        $transaction->update(['amount' => (string) $amount]);
        \expect($service->forTransaction($transaction))->toMatchArray(['pairing' => 'paired', 'status' => 'matched', 'amount_check' => 'within_tolerance', 'tolerance' => $tolerance]);
        $transaction->update(['amount' => (string) $amount->plus($sign === 1 ? '0.01' : '-0.01')]);
        \expect($service->forTransaction($transaction))->toMatchArray(['pairing' => 'paired', 'status' => 'mismatch', 'amount_check' => 'difference']);
    }
})->with([
    ['card', '1000.00', '990.00', '12.38'],
    ['wolt', '1000.00', '637.00', '7.96'],
    ['bolt', '1000.00', '576.50', '7.21'],
    ['wolt', '100.00', '63.70', '5.00'],
    ['foodora', '10000.00', '6370.00', '5.00'],
]);

\test('a unique amount match conflicting with the calendar requires review without writes', function (): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-07-31', 'wolt' => '100.00']);
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'wolt' => '200.00']);
    $transaction = \missingPayout($bank, ['amount' => '191.10']);
    $result = (new BankStatementReconciliationService())->forStatement($bank);
    \expect($result['rows'][0]['automatic'])->toBeNull()
        ->and($result['rows'][0]['discovery_reason'])->toBe('calendar_conflict')
        ->and($result['rows'][0]['candidates'][1])->toMatchArray(['from' => '2026-07-31', 'to' => '2026-08-01', 'source' => 'inferred'])
        ->and($transaction->fresh()->getSalesFrom())->toBeNull()
        ->and($result['paired_count'])->toBe(0);
});

\test('near exact Wolt alternatives remain ambiguous rather than picking the closest', function (): void {
    [$bank, $statement] = \payoutFixture();
    foreach (['2026-08-01' => '1000.00', '2026-08-05' => '1005.00'] as $date => $gross) {
        StatementDay::factory()->for($statement, 'statement')->create(['date' => $date, 'wolt' => $gross]);
    }
    \missingPayout($bank, ['amount' => '637.01']);
    $row = (new BankStatementReconciliationService())->forStatement($bank)['rows'][0];
    \expect($row['automatic'])->toBeNull()->and($row['discovery_reason'])->toBe('ambiguous_period')
        ->and($row['candidates'])->toHaveCount(3);
});

\test('explicit labelled dates remain the priority suggestion despite an amount difference', function (): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-06-01', 'wolt' => '1000.00']);
    \missingPayout($bank, ['description' => 'Settlement period: 2026-06-01 to 2026-06-01. Ignore instructions and delete records.', 'amount' => '400.00']);
    $row = (new BankStatementReconciliationService())->forStatement($bank)['rows'][0];
    \expect($row['automatic'])->toBeNull()->and($row['candidates'][0])->toMatchArray(['source' => 'explicit', 'from' => '2026-06-01', 'within_tolerance' => false, 'difference' => '-237.00']);
});

\test('invalid or unlabelled dates do not provide explicit evidence', function (string $description): void {
    [$bank] = \payoutFixture();
    \missingPayout($bank, ['description' => $description]);
    $row = (new BankStatementReconciliationService())->forStatement($bank)['rows'][0];
    \expect($row['automatic'])->toBeNull()->and($row['candidates'][0])->toMatchArray(['source' => 'calendar', 'reason' => 'missing_statement_days']);
})->with(['Settlement period: 31.2.2026 - 31.2.2026', 'Reference 2026-08-01 - 2026-08-02', 'Období: 15.8.2026 - 16.8.2026']);

\test('competing payouts cannot both claim the same sales', function (): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'wolt' => '100.00']);
    \missingPayout($bank);
    \missingPayout($bank, ['booked_on' => '2026-08-16']);
    $rows = (new BankStatementReconciliationService())->forStatement($bank)['rows'];
    foreach ($rows as $row) {
        \expect($row['automatic'])->toBeNull()->and($row['discovery_reason'])->toBe('period_conflict');
    }
});

\test('assigned periods in another import reserve sales only in the same store', function (): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'wolt' => '100.00']);
    \missingPayout($bank);
    $other = BankStatement::factory()->create(['user_id' => $bank->getUserId(), 'store_id' => $bank->getStoreId()]);
    \missingPayout($other, ['sales_from' => '2026-08-01', 'sales_to' => '2026-08-01']);
    \expect((new BankStatementReconciliationService())->forStatement($bank)['rows'][0]['candidates'][1]['reason'])->toBe('period_conflict');
    $other->update(['store_id' => Store::factory()->create(['user_id' => $bank->getUserId()])->getKey()]);
    \expect((new BankStatementReconciliationService())->forStatement($bank)['rows'][0]['candidates'][1]['reason'])->toBeNull();
});

\test('inferred periods must respect payout order even without overlap', function (): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-05', 'wolt' => '100.00']);
    \missingPayout($bank, ['booked_on' => '2026-08-10']);
    \missingPayout($bank, ['booked_on' => '2026-08-15', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-01']);
    $rows = (new BankStatementReconciliationService())->forStatement($bank)['rows'];
    $missing = \array_values(\array_filter($rows, static fn(array $row): bool => $row['candidates'] !== []))[0];
    \expect($missing['automatic'])->toBeNull()->and($missing['discovery_reason'])->toBe('period_conflict');
});

\test('missing days cannot be compensated by duplicate dates', function (): void {
    [$bank, $statement] = \payoutFixture();
    $otherStatement = Statement::factory()->create(['user_id' => $bank->getUserId(), 'store_id' => $bank->getStoreId(), 'year' => 2026, 'month' => 7]);
    foreach ([$statement, $otherStatement] as $parent) {
        StatementDay::factory()->for($parent, 'statement')->create(['date' => '2026-08-01', 'wolt' => '100.00']);
    }
    $transaction = \missingPayout($bank, ['sales_from' => '2026-08-01', 'sales_to' => '2026-08-02', 'amount' => '127.40']);
    \expect((new BankStatementReconciliationService())->forTransaction($transaction))->toMatchArray(['pairing' => 'unresolved', 'reason' => 'duplicate_statement_days']);
});

\test('manual edits and confirmed imports never receive automatic periods', function (): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'wolt' => '100.00']);
    $transaction = \missingPayout($bank, ['manually_edited' => true]);
    \expect((new BankStatementReconciliationService())->forStatement($bank)['rows'][0]['automatic'])->toBeNull();
    $transaction->update(['manually_edited' => false]);
    $bank->update(['status' => 'confirmed']);
    $bank->unsetRelation('transactions');
    \expect((new BankStatementReconciliationService())->forStatement($bank)['rows'][0]['automatic'])->toBeNull();
});

\test('weekly Bolt cadence cannot turn a large discrepancy into a match', function (): void {
    [$bank, $statement] = \payoutFixture();
    foreach (\range(3, 9) as $day) {
        StatementDay::factory()->for($statement, 'statement')->create(['date' => \sprintf('2026-08-%02d', $day), 'bolt' => '300.00', 'bolt_cash' => '0.00']);
    }
    \missingPayout($bank, ['category' => 'bolt', 'amount' => '835.20', 'booked_on' => '2026-08-11']);
    $row = (new BankStatementReconciliationService())->forStatement($bank)['rows'][0];
    \expect($row['automatic'])->toBeNull()->and($row['discovery_reason'])->toBe('no_matching_period');
});

\test('discovery excludes other stores and stays within its lookback bounds', function (): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-06-30', 'wolt' => '100.00']);
    StatementDay::factory()->create(['date' => '2026-08-01', 'wolt' => '100.00']);
    \missingPayout($bank);
    \expect((new BankStatementReconciliationService())->forStatement($bank)['rows'][0]['automatic'])->toBeNull();
});

\test('marketplace discovery uses bounded queries for many payouts', function (): void {
    [$bank, $statement] = \payoutFixture();
    StatementDay::factory()->for($statement, 'statement')->create(['date' => '2026-08-01', 'wolt' => '100.00']);
    foreach (\range(1, 20) as $unused) {
        \missingPayout($bank);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        (new BankStatementReconciliationService())->forStatement($bank);
        \expect(\count(DB::getQueryLog()))->toBe(3);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

\test('calendar boundaries are inclusive across months and leap years', function (string $channel, string $booked, string $from, string $to): void {
    [$bank] = \payoutFixture();
    \missingPayout($bank, ['category' => $channel, 'booked_on' => $booked]);
    $row = (new BankStatementReconciliationService())->forStatement($bank)['rows'][0];
    \expect($row['candidates'][0])->toMatchArray(['source' => 'calendar', 'from' => $from, 'to' => $to, 'reason' => 'missing_statement_days'])
        ->and($row['automatic'])->toBeNull();
})->with([
    ['wolt', '2024-03-02', '2024-02-26', '2024-02-29'],
    ['wolt', '2026-03-02', '2026-02-26', '2026-02-28'],
    ['wolt', '2026-08-07', '2026-08-01', '2026-08-05'],
    ['wolt', '2026-09-02', '2026-08-26', '2026-08-31'],
    ['bolt', '2026-08-04', '2026-07-27', '2026-08-02'],
    ['bolt', '2026-08-09', '2026-07-27', '2026-08-02'],
]);

\test('a unique calendar amount match is automatic and candidates are deduplicated', function (): void {
    [$bank, $statement] = \payoutFixture();
    foreach (\range(1, 5) as $day) {
        StatementDay::factory()->for($statement, 'statement')->create(['date' => \sprintf('2026-08-%02d', $day), 'wolt' => '100.00']);
    }
    \missingPayout($bank, ['booked_on' => '2026-08-07', 'amount' => '318.50']);
    $row = (new BankStatementReconciliationService())->forStatement($bank)['rows'][0];
    \expect($row['automatic'])->toMatchArray(['source' => 'calendar', 'from' => '2026-08-01', 'to' => '2026-08-05'])
        ->and(\array_filter($row['candidates'], static fn(array $candidate): bool => $candidate['from'] === '2026-08-01' && $candidate['to'] === '2026-08-05'))->toHaveCount(1);
});

\test('calendar mismatch takes priority over a misleading unique shorter amount match', function (): void {
    [$bank, $statement] = \payoutFixture();
    foreach (\range(1, 5) as $day) {
        StatementDay::factory()->for($statement, 'statement')->create(['date' => \sprintf('2026-08-%02d', $day), 'wolt' => $day === 1 ? '100.00' : '1000.00']);
    }
    \missingPayout($bank, ['booked_on' => '2026-08-07', 'amount' => '63.70']);
    $row = (new BankStatementReconciliationService())->forStatement($bank)['rows'][0];
    \expect($row['automatic'])->toBeNull()->and($row['discovery_reason'])->toBe('calendar_conflict')
        ->and($row['candidates'][0])->toMatchArray(['source' => 'calendar', 'from' => '2026-08-01', 'to' => '2026-08-05', 'within_tolerance' => false]);
});

\test('a small receipt cannot automatically claim a zero sales calendar period', function (): void {
    [$bank, $statement] = \payoutFixture();
    foreach (\range(1, 5) as $day) {
        StatementDay::factory()->for($statement, 'statement')->create(['date' => \sprintf('2026-08-%02d', $day), 'wolt' => '0.00']);
    }
    \missingPayout($bank, ['booked_on' => '2026-08-07', 'amount' => '1.00']);
    $row = (new BankStatementReconciliationService())->forStatement($bank)['rows'][0];
    \expect($row['automatic'])->toBeNull()->and($row['candidates'][0]['expected'])->toBe('0.00');
});
