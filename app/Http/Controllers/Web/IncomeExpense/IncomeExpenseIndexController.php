<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\IncomeExpense;

use App\Http\Controllers\Web\Concerns\ResolvesFinancialReportContext;
use App\Models\Store;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class IncomeExpenseIndexController
{
    use ResolvesFinancialReportContext;

    /**
     * Monthly reports never expose an unbounded list.
     */
    public const int TAKE = 1;

    /**
     * Render the active store's monthly financial report.
     */
    public function __invoke(Request $request): Response
    {
        $admin = User::mustAuth();
        $now = Carbon::now();
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            $year = $now->year;
            $month = $now->month;
        }
        $store = ActiveStoreResolver::resolveIncludingInactive($request, $admin);
        $service = new FinancialReportService();

        return Inertia::render('income-expenses/Index', [
            'active_store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'is_warehouse' => $store->isWarehouse(),
                'is_active' => $store->isActive(),
            ] : null,
            'filters' => ['year' => $year, 'month' => $month],
            'financial_report' => $store instanceof Store && !$store->isWarehouse()
                ? $service->build($admin, $store, $year, $month)
                : null,
        ]);
    }
}
