<?php

declare(strict_types=1);

use App\Enums\PayrollAdjustmentTypeEnum;
use App\Models\AttendanceSession;
use App\Models\FinancialReport;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use App\Services\PayrollReportService;
use Illuminate\Validation\ValidationException;

\test('build calculates a payslip from rounded planned shifts', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'name' => 'Retail']);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Anna',
        'last_name' => 'Nováková',
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-10',
        'start_time' => '08:00',
        'end_time' => '09:01',
        'hourly_rate' => 100,
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-11',
        'start_time' => '08:00',
        'end_time' => '09:01',
        'hourly_rate' => 100,
    ]);

    $report = (new PayrollReportService())->build($admin, $store, 2026, 7);

    \expect($report['status'])->toBe('open')
        ->and($report['payslips'])->toHaveCount(1)
        ->and($report['payslips'][0]['worker_name'])->toBe('Anna Nováková')
        ->and($report['payslips'][0]['planned_minutes'])->toBe(122)
        ->and($report['payslips'][0]['base_amount'])->toBe(203.34)
        ->and($report['payslips'][0]['final_amount'])->toBe(203.34)
        ->and($report['payslips'][0]['shifts'][0]['amount'])->toBe(101.67);
});

\test('closing snapshots payroll and reopening requires open finances', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-10',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'hourly_rate' => 100,
    ]);
    $service = new PayrollReportService();

    $service->close($admin, $store, 2026, 7);
    $shift->update(['end_time' => '10:00']);

    \expect($service->build($admin, $store, 2026, 7)['payslips'][0]['base_amount'])->toEqual(100.0)
        ->and(fn() => $service->createAdjustment(
            $admin,
            $store,
            2026,
            7,
            $worker,
            PayrollAdjustmentTypeEnum::TIP,
            10,
            'Late tip',
        ))->toThrow(ValidationException::class);

    FinancialReport::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'year' => 2026,
        'month' => 7,
        'status' => 'closed',
    ]);
    \expect(fn() => $service->reopen($admin, $store, 2026, 7))->toThrow(ValidationException::class);

    FinancialReport::query()->update(['status' => 'open']);
    $service->reopen($admin, $store, 2026, 7);
    \expect($service->build($admin, $store, 2026, 7)['payslips'][0]['base_amount'])->toBe(200.0);
});

\test('build exposes actual attendance and warnings without changing planned pay', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-10',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'hourly_rate' => 100,
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'hourly_rate' => 100,
        'started_at' => '2026-07-10 06:00:00',
        'ended_at' => '2026-07-10 07:30:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'shift_id' => null,
        'created_by_user_id' => $admin->getKey(),
        'active_worker_id' => $worker->getKey(),
        'hourly_rate' => 100,
        'started_at' => '2026-07-12 06:00:00',
        'ended_at' => null,
    ]);

    $payslip = (new PayrollReportService())->build($admin, $store, 2026, 7)['payslips'][0];

    \expect($payslip['base_amount'])->toBe(200.0)
        ->and($payslip['final_amount'])->toBe(200.0)
        ->and($payslip['actual_seconds'])->toBe(5400)
        ->and($payslip['incomplete_count'])->toBe(1)
        ->and($payslip['unmatched_count'])->toBe(1)
        ->and($payslip['attendance'])->toHaveCount(2);
});

\test('adjustments change final pay and deductions cannot make it negative', function (): void {
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
    $service = new PayrollReportService();

    $service->createAdjustment($admin, $store, 2026, 7, $worker, PayrollAdjustmentTypeEnum::TIP, 25, 'Shared tips');
    $service->createAdjustment($admin, $store, 2026, 7, $worker, PayrollAdjustmentTypeEnum::DEDUCTION, 20, 'Till difference');
    $payslip = $service->build($admin, $store, 2026, 7)['payslips'][0];

    \expect($payslip['tip_amount'])->toBe(25.0)
        ->and($payslip['deduction_amount'])->toBe(20.0)
        ->and($payslip['final_amount'])->toBe(105.0)
        ->and($payslip['adjustments'])->toHaveCount(2)
        ->and(fn() => $service->createAdjustment(
            $admin,
            $store,
            2026,
            7,
            $worker,
            PayrollAdjustmentTypeEnum::DEDUCTION,
            106,
            'Invalid',
        ))->toThrow(ValidationException::class);
});
