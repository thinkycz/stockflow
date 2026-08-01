<?php

declare(strict_types=1);

use App\Enums\FinancialDirectionEnum;
use App\Enums\FinancialSourceTypeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\FinancialReportManualRow;
use App\Models\Shift;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Worker;
use App\Services\FinancialReportService;

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
