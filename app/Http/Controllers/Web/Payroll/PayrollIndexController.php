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

class PayrollIndexController
{
    /**
     * Monthly payroll exposes one bounded aggregate report.
     */
    public const int TAKE = 1;

    /**
     * Render the active store's payroll report.
     */
    public function __invoke(Request $request): Response
    {
        $admin = User::mustAuth();
        $now = CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE);
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            $year = $now->year;
            $month = $now->month;
        }
        $store = ActiveStoreResolver::resolve($request, $admin);

        return Inertia::render('payroll/Index', [
            'active_store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'is_warehouse' => $store->isWarehouse(),
            ] : null,
            'filters' => ['year' => $year, 'month' => $month],
            'payroll_report' => $store instanceof Store && !$store->isWarehouse()
                ? (new PayrollReportService())->build($admin, $store, $year, $month)
                : null,
        ]);
    }
}
