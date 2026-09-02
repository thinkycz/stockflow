<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use App\Models\Store;
use App\Models\User;
use App\Services\InventoryReportService;
use App\Services\StatementService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ReportController
{
    /**
     * Render the reports page.
     *
     * Every metric on this page is scoped to the currently active
     * store. Without an active store the page returns an empty payload
     * and the frontend renders an explanatory empty state.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $now = Carbon::now();
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;
        $start = $now->copy()->setDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $cutoff = $end->isFuture() ? $now : $end;
        $activeStore = ActiveStoreResolver::resolveIncludingInactive($request, $user);
        $statementService = Resolver::resolve(StatementService::class);
        $inventoryService = Resolver::resolve(InventoryReportService::class);
        $financialReport = $statementService->buildReport($user, $activeStore?->getKey(), $year, $month);
        $inventoryReport = $activeStore instanceof Store
            ? $inventoryService->build($user, $activeStore, $start, $cutoff)
            : $inventoryService->empty($cutoff);
        $inventoryCurrent = Typer::assertArray($inventoryReport['current_inventory']);

        return Inertia::render('reports/Index', [
            'active_store' => $activeStore instanceof Store ? [
                'id' => $activeStore->getKey(),
                'name' => $activeStore->getName(),
            ] : null,
            'filter' => [
                'store_id' => $activeStore?->getKey(),
                'year' => $year,
                'month' => $month,
            ],
            'summary' => [
                'total_revenue' => $financialReport['totals']['total_revenue'],
                'gross_margin' => $financialReport['totals']['gross_margin'],
                'consumption_cost' => $financialReport['totals']['investment'],
                'inventory_value' => Typer::parseFloat($inventoryCurrent['value']),
            ],
            'financial_report' => $financialReport,
            'inventory_report' => $inventoryReport,
        ]);
    }
}
