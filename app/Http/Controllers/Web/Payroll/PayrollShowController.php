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

class PayrollShowController
{
    /**
     * Render one worker's payslip detail for the selected month.
     */
    public function __invoke(Request $request, int $worker): Response
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
        $detail = (new PayrollReportService())->buildDetail($admin, $store, $year, $month, $worker);
        if ($detail === null) {
            \abort(404);
        }
        $report = Typer::assertArray($detail['report']);

        return Inertia::render('payroll/Show', [
            'active_store' => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'is_warehouse' => $store->isWarehouse(),
            ],
            'filters' => ['year' => $year, 'month' => $month],
            'report' => [
                'id' => $report['id'],
                'status' => $report['status'],
                'closed_at' => $report['closed_at'],
                'reopened_at' => $report['reopened_at'],
            ],
            'payslip' => Typer::assertArray($detail['payslip']),
        ]);
    }
}
