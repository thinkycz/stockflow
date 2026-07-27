<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use App\Models\Store;
use App\Models\User;
use App\Services\StatementService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
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
    public function __invoke(Request $request, StatementService $statementService): Response
    {
        $user = User::mustAuth();
        $now = Carbon::now();
        $allTime = $request->query('all_time') === '1' || $request->query('period') === 'all';
        $year = $allTime ? null : (Typer::parseNullableInt($request->query('year')) ?? $now->year);
        $month = $allTime ? null : (Typer::parseNullableInt($request->query('month')) ?? $now->month);

        $activeStore = ActiveStoreResolver::resolve($request, $user);

        if (!$activeStore instanceof Store) {
            return Inertia::render('reports/Index', $this->emptyPayload($user, $statementService, $allTime, $year, $month));
        }

        $storeId = $activeStore->getKey();
        $statementReport = $statementService->buildReport($user, $storeId, $year, $month);

        return Inertia::render('reports/Index', [
            'active_store' => [
                'id' => $activeStore->getKey(),
                'name' => $activeStore->getName(),
            ],
            'statement_report' => $statementReport,
            'statement_filter' => [
                'all_time' => $allTime,
                'store_id' => $storeId,
                'year' => $year,
                'month' => $month,
            ],
        ]);
    }

    /**
     * Build the payload that the frontend renders when no store is
     * active. Numeric metrics are zeroed out and lists are empty so
     * the page is safe to render without errors.
     *
     * @return array<string, mixed>
     */
    private function emptyPayload(User $user, StatementService $statementService, bool $allTime, int|null $year, int|null $month): array
    {
        $statementReport = $statementService->buildReport($user, null, $year, $month);

        return [
            'active_store' => null,
            'statement_report' => $statementReport,
            'statement_filter' => [
                'all_time' => $allTime,
                'store_id' => null,
                'year' => $year,
                'month' => $month,
            ],
        ];
    }
}
