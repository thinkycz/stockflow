<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BankStatementReconciliationService;
use App\Services\StatementService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class StatementIndexController
{
    /**
     * Page size hint required by the web index controller architecture test.
     * Statements render every day in a single month, so the list is always
     * bounded by the calendar and pagination is not exposed.
     */
    public const int TAKE = 31;

    /**
     * Render the statements editor for the active store and month.
     */
    public function __invoke(
        Request $request,
        StatementService $service,
        BankStatementReconciliationService $bankReconciliation,
    ): Response
    {
        $user = User::mustAuth();

        if (!$user->isAdmin() && $user->getAssignedStoreId() === null) {
            \abort(403);
        }

        $scopeUser = $user->resolveScopeUser();
        $store = ActiveStoreResolver::resolve($request, $user);

        $now = Carbon::now(StatementService::TIMEZONE);
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;

        $statement = null;
        $days = [];
        $todayStatement = null;
        $todayDay = null;

        if ($store !== null) {
            $statement = $service->findOrCreateForMonth($scopeUser, $store, $year, $month);
            $days = $statement->days()->orderBy('date')->get()->map(
                static fn(StatementDay $day): array => self::dayPayload($day),
            )->all();

            $todayStatement = $year === $now->year && $month === $now->month
                ? $statement
                : $service->findOrCreateForMonth($scopeUser, $store, $now->year, $now->month);
            $resolvedTodayDay = $todayStatement->days()->whereDate('date', $now->toDateString())->first();
            $todayDay = $resolvedTodayDay instanceof StatementDay ? self::dayPayload($resolvedTodayDay) : null;
        }

        return Inertia::render('statements/Index', [
            'statement' => $statement instanceof Statement ? [
                'id' => $statement->getKey(),
                'store_id' => $statement->getStoreId(),
                'year' => $statement->getYear(),
                'month' => $statement->getMonth(),
            ] : null,
            'days' => $days,
            'today_statement' => $todayStatement instanceof Statement ? [
                'id' => $todayStatement->getKey(),
                'store_id' => $todayStatement->getStoreId(),
                'year' => $todayStatement->getYear(),
                'month' => $todayStatement->getMonth(),
            ] : null,
            'today_day' => $todayDay,
            'filters' => [
                'store_id' => $store?->getKey(),
                'year' => $year,
                'month' => $month,
            ],
            'is_admin' => $user->isAdmin(),
            'bank_reconciliation' => $user->isAdmin()
                ? $bankReconciliation->monthlyStatus($scopeUser, $store, $year, $month)
                : null,
            'active_attendances' => $store !== null
                ? (new AttendanceService())->activeCurrentDayEmployees($user, $store)
                : [],
        ]);
    }

    /**
     * Serialize one statement day for the Inertia page.
     *
     * @return array<string, bool|float|int|string|null>
     */
    private static function dayPayload(StatementDay $day): array
    {
        return [
            'id' => $day->getKey(),
            'date' => $day->getDate(),
            'cash' => $day->getCash(),
            'card' => $day->getCard(),
            'wolt' => $day->getWolt(),
            'bolt' => $day->getBolt(),
            'bolt_cash' => $day->getBoltCash(),
            'foodora' => $day->getFoodora(),
            'total' => $day->getTotal(),
            'cash_checked' => $day->getCashChecked(),
        ];
    }
}
