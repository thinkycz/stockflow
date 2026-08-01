<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Models\Store;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\PayrollReportService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class PayrollPrintController
{
    /**
     * Render printable payslips for the selected month.
     */
    public function __invoke(Request $request): Response
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store || $store->isWarehouse()) {
            \abort(404);
        }
        $now = CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE);
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            $year = $now->year;
            $month = $now->month;
        }
        $report = (new PayrollReportService())->build($admin, $store, $year, $month);
        $workerId = Typer::parseNullableInt($request->query('worker_id'));
        if ($workerId !== null) {
            $payslips = Typer::assertArray($report['payslips'] ?? null);
            $report['payslips'] = \array_values(\array_filter(
                $payslips,
                static fn(mixed $payslip): bool => $workerId === Typer::assertInt(
                    Typer::assertArray($payslip)['worker_id'] ?? null,
                ),
            ));
        }

        return Inertia::render('payroll/Print', [
            'active_store' => ['id' => $store->getKey(), 'name' => $store->getName()],
            'payroll_report' => $report,
        ]);
    }
}
