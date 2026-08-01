<?php

declare(strict_types=1);

use App\Enums\FinancialDirectionEnum;
use App\Enums\FinancialSourceTypeEnum;
use App\Enums\PayrollAdjustmentTypeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\FinancialReportManualRow;
use App\Models\Shift;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Worker;
use App\Services\FinancialReportService;
use App\Services\PayrollReportService;
use Illuminate\Validation\ValidationException;

\test('build calculates revenue commissions stock documents and wages', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Retail']);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 7)->create();
    StatementDay::factory()->create([
        'statement_id' => $statement->getKey(), 'date' => '2026-07-10',
        'cash' => 100, 'card' => 100, 'bolt' => 100, 'bolt_cash' => 50,
        'wolt' => 100, 'foodora' => 100, 'total' => 550,
    ]);
    StockMovement::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(),
        'number' => 'IN-2026-0001', 'type' => StockMovementTypeEnum::INCOMING->value,
        'occurred_at' => '2026-07-12 10:00:00', 'total_value' => 40,
    ]);
    StockMovement::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(),
        'number' => 'TR-2026-0001', 'type' => StockMovementTypeEnum::TRANSFER->value,
        'occurred_at' => '2026-07-13 10:00:00', 'total_value' => 30,
    ]);
    StockMovement::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(),
        'number' => 'IN-2026-0002', 'type' => StockMovementTypeEnum::INCOMING->value,
        'occurred_at' => '2026-07-14 10:00:00', 'total_value' => 20,
        'reversed_at' => '2026-07-15 10:00:00',
    ]);
    StockMovement::factory()->consumption($store)->create([
        'user_id' => $admin->getKey(), 'number' => 'CON-2026-0001',
        'occurred_at' => '2026-07-14 10:00:00', 'total_value' => 99,
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Anna', 'last_name' => 'Nováková']);
    Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00', 'hourly_rate' => 200,
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-11', 'start_time' => '08:00', 'end_time' => '12:00', 'hourly_rate' => 100,
    ]);

    $report = (new FinancialReportService())->build($admin, $store, 2026, 7);

    \expect($report['income_rows'])->toHaveCount(5)
        ->and($report['income_rows'][0]['effective_amount'])->toBe(100.0)
        ->and($report['income_rows'][1]['details']['commission_amount'])->toBe(1.0)
        ->and($report['income_rows'][2]['calculated_amount'])->toBe(105.0)
        ->and($report['totals']['income'])->toBe(444.0)
        ->and($report['expense_rows'])->toHaveCount(3)
        ->and($report['totals']['expenses'])->toBe(2070.0)
        ->and($report['totals']['profit'])->toBe(-1626.0);
});

\test('financial wages use payslip totals and closing requires closed payroll', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-10',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'hourly_rate' => 100,
    ]);
    $payroll = new PayrollReportService();
    $payroll->upsertWageOverride($admin, $store, 2026, 7, $worker, 3.5, 120);
    $payroll->createAdjustment(
        $admin,
        $store,
        2026,
        7,
        $worker,
        PayrollAdjustmentTypeEnum::TIP,
        25,
        'Shared tips',
    );
    $finance = new FinancialReportService();

    $wage = \collect($finance->build($admin, $store, 2026, 7)['expense_rows'])
        ->firstWhere('source_type', FinancialSourceTypeEnum::WAGE->value);

    \expect($wage['calculated_amount'])->toBe(445.0)
        ->and($wage['details']['minutes'])->toBe(210)
        ->and($wage['details']['hourly_rate'])->toBe(120.0)
        ->and($wage['details']['wage_overridden'])->toBeTrue()
        ->and(fn() => $finance->close($admin, $store, 2026, 7))->toThrow(ValidationException::class);

    $payroll->close($admin, $store, 2026, 7);
    $finance->close($admin, $store, 2026, 7);

    \expect($finance->build($admin, $store, 2026, 7)['report']['status'])->toBe('closed');
});

\test('revenue commission is rounded once from the monthly gross amount', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 7)->create();
    StatementDay::factory()->create(['statement_id' => $statement->getKey(), 'date' => '2026-07-01', 'card' => 0.50, 'total' => 0.50]);
    StatementDay::factory()->create(['statement_id' => $statement->getKey(), 'date' => '2026-07-02', 'card' => 0.50, 'total' => 0.50]);

    $card = (new FinancialReportService())->build($admin, $store, 2026, 7)['income_rows'][1];

    \expect($card['details']['gross_amount'])->toBe(1.0)
        ->and($card['details']['commission_amount'])->toBe(0.01)
        ->and($card['effective_amount'])->toBe(0.99);
});

\test('override persists source changes and reopening refreshes a closed snapshot', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 7)->create();
    $day = StatementDay::factory()->create(['statement_id' => $statement->getKey(), 'date' => '2026-07-01', 'cash' => 100, 'total' => 100]);
    $service = new FinancialReportService();

    $service->setOverride($admin, $store, 2026, 7, FinancialSourceTypeEnum::REVENUE, 'cash', 80);
    $day->update(['cash' => 200, 'total' => 200]);
    \expect($service->build($admin, $store, 2026, 7)['income_rows'][0]['effective_amount'])->toBe(80.0);

    (new PayrollReportService())->close($admin, $store, 2026, 7);
    $service->close($admin, $store, 2026, 7);
    $day->update(['cash' => 300, 'total' => 300]);
    \expect($service->build($admin, $store, 2026, 7)['income_rows'][0]['calculated_amount'])->toEqual(200.0);

    $service->reopen($admin, $store, 2026, 7);
    $reopened = $service->build($admin, $store, 2026, 7);
    \expect($reopened['income_rows'][0]['calculated_amount'])->toBe(300.0)
        ->and($reopened['income_rows'][0]['effective_amount'])->toBe(80.0);
});

\test('manual rows copy idempotently and clamp the day to the target month', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $service = new FinancialReportService();
    $service->createManualRow($admin, $store, 2026, 1, FinancialDirectionEnum::EXPENSE, 'Rent', '2026-01-31', 1000, null);

    \expect($service->copyPreviousManualRows($admin, $store, 2026, 2))->toBe(1)
        ->and($service->copyPreviousManualRows($admin, $store, 2026, 2))->toBe(0)
        ->and(FinancialReportManualRow::query()->latest('id')->firstOrFail()->getOccurredOn())->toBe('2026-02-28');
});

\test('recurring expenses generate one store scoped row and clamp the due day', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $service = new FinancialReportService();
    $service->createRecurringExpense($admin, $store, 2026, 1, 'Rent', 15000, 31, 'Lease');

    $before = $service->build($admin, $store, 2025, 12)['expense_rows'];
    $february = \collect($service->build($admin, $store, 2026, 2)['expense_rows'])
        ->firstWhere('source_type', FinancialSourceTypeEnum::RECURRING_EXPENSE->value);
    $other = \collect($service->build($admin, $otherStore, 2026, 2)['expense_rows'])
        ->firstWhere('source_type', FinancialSourceTypeEnum::RECURRING_EXPENSE->value);

    \expect($before)->toBeEmpty()
        ->and($february['label'])->toBe('Rent')
        ->and($february['occurred_on'])->toBe('2026-02-28')
        ->and($february['calculated_amount'])->toBe(15000.0)
        ->and($february['note'])->toBe('Lease')
        ->and($other)->toBeNull();
});

\test('recurring expense versions preserve earlier and scheduled later months before termination', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $service = new FinancialReportService();
    $expense = $service->createRecurringExpense($admin, $store, 2026, 1, 'Rent', 100, 31, 'Original');
    $service->changeRecurringExpense($admin, $store, $expense->getKey(), 2026, 3, 'Rent', 120, 15, 'March');
    $service->changeRecurringExpense($admin, $store, $expense->getKey(), 2026, 5, 'Rent', 140, 5, 'May');
    $service->changeRecurringExpense($admin, $store, $expense->getKey(), 2026, 4, 'Rent', 130, 10, 'April');
    $service->terminateRecurringExpense($admin, $store, $expense->getKey(), 2026, 6);

    $row = static fn(int $month): mixed => \collect($service->build($admin, $store, 2026, $month)['expense_rows'])
        ->firstWhere('source_type', FinancialSourceTypeEnum::RECURRING_EXPENSE->value);

    \expect($row(2)['calculated_amount'])->toBe(100.0)
        ->and($row(2)['note'])->toBe('Original')
        ->and($row(3)['calculated_amount'])->toBe(120.0)
        ->and($row(3)['occurred_on'])->toBe('2026-03-15')
        ->and($row(4)['calculated_amount'])->toBe(130.0)
        ->and($row(5)['calculated_amount'])->toBe(140.0)
        ->and($row(6))->toBeNull();
});

\test('recurring expense uses monthly overrides and closed snapshots refresh only after reopen', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $service = new FinancialReportService();
    $expense = $service->createRecurringExpense($admin, $store, 2026, 7, 'Internet', 100, 10, null);
    $service->setOverride(
        $admin,
        $store,
        2026,
        7,
        FinancialSourceTypeEnum::RECURRING_EXPENSE,
        (string) $expense->getKey(),
        90,
    );
    (new PayrollReportService())->close($admin, $store, 2026, 7);
    $service->close($admin, $store, 2026, 7);
    $service->changeRecurringExpense($admin, $store, $expense->getKey(), 2026, 7, 'Internet', 200, 10, null);

    $closed = \collect($service->build($admin, $store, 2026, 7)['expense_rows'])
        ->firstWhere('source_type', FinancialSourceTypeEnum::RECURRING_EXPENSE->value);
    $service->reopen($admin, $store, 2026, 7);
    $service->resetOverride(
        $admin,
        $store,
        2026,
        7,
        FinancialSourceTypeEnum::RECURRING_EXPENSE,
        (string) $expense->getKey(),
    );
    $reopened = \collect($service->build($admin, $store, 2026, 7)['expense_rows'])
        ->firstWhere('source_type', FinancialSourceTypeEnum::RECURRING_EXPENSE->value);

    \expect($closed['calculated_amount'])->toEqual(100.0)
        ->and($closed['effective_amount'])->toEqual(90.0)
        ->and($reopened['calculated_amount'])->toBe(200.0)
        ->and($reopened['effective_amount'])->toBe(200.0);
});
