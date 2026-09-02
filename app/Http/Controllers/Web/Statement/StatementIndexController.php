<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
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
        $store = ActiveStoreResolver::resolveIncludingInactive($request, $user);

        $now = Carbon::now(StatementService::TIMEZONE);
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;

        $statement = null;
        $days = [];
        $todayStatement = null;
        $todayDay = null;

        if ($store instanceof Store) {
            if ($store->isActive()) {
                $statement = $service->findOrCreateForMonth($scopeUser, $store, $year, $month);
            } else {
                $query = Statement::query();
                Statement::scopeForUser($query, $scopeUser);
                Statement::scopeForStore($query, $store->getKey());
                Statement::scopeForMonth($query, $year, $month);
                $statement = $query->first();
            }
        }

        if ($statement instanceof Statement) {
            $days = $statement->days()->orderBy('date')->get()->map(
                static fn(StatementDay $day): array => self::dayPayload($day),
            )->all();
        }

        if ($store instanceof Store && $store->isActive()) {
            $todayStatement = $year === $now->year && $month === $now->month
                ? $statement
                : $service->findOrCreateForMonth($scopeUser, $store, $now->year, $now->month);
            if ($todayStatement instanceof Statement) {
                $resolvedTodayDay = $todayStatement->days()->whereDate('date', $now->toDateString())->first();
                $todayDay = $resolvedTodayDay instanceof StatementDay ? self::dayPayload($resolvedTodayDay) : null;
            }
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
            'store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'is_active' => $store->isActive(),
            ] : null,
            'editable' => $store?->isActive() ?? false,
            'filters' => [
                'store_id' => $store?->getKey(),
                'year' => $year,
                'month' => $month,
            ],
            'is_admin' => $user->isAdmin(),
            'bank_reconciliation' => $user->isAdmin()
                ? $bankReconciliation->monthlyStatus($scopeUser, $store, $year, $month)
                : null,
            'active_attendances' => $store instanceof Store && $store->isActive()
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
