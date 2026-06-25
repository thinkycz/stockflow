<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

use App\Http\Controllers\Web\Concerns\ResolvesDefaultStore;
use App\Http\Controllers\Web\Concerns\ResolvesUserScope;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\User;
use App\Services\StatementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class StatementIndexController
{
    use ResolvesDefaultStore;
    use ResolvesUserScope;

    /**
     * Page size hint required by the web index controller architecture test.
     * Statements render every day in a single month, so the list is always
     * bounded by the calendar and pagination is not exposed.
     */
    public const int TAKE = 31;

    /**
     * Render the statements editor for the selected store and month.
     */
    public function __invoke(Request $request, StatementService $service): Response
    {
        $user = User::mustAuth();
        $isLimited = !$user->isAdmin();
        $assignedStoreId = $isLimited ? $user->getAssignedStoreId() : null;

        if ($isLimited && $assignedStoreId === null) {
            \abort(403);
        }

        $scopeUser = $this->resolveScopeUser($user);
        $storesQuery = Store::query();
        Store::scopeForUser($storesQuery, $scopeUser);
        $stores = Store::querySelect($storesQuery)
            ->orderBy('name')
            ->get()
            ->all();

        $requestedStoreId = Typer::parseNullableInt($request->query('store_id'));
        $defaultStore = $this->resolveDefaultStore($stores, $user);
        $storeId = $requestedStoreId ?? $defaultStore?->getKey();

        if ($isLimited && $storeId !== null && $storeId !== $assignedStoreId) {
            \abort(403);
        }

        $now = \Illuminate\Support\Carbon::now();
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;

        $store = null;
        if ($storeId !== null) {
            $storeLookup = Store::query();
            Store::scopeForUser($storeLookup, $scopeUser);
            $store = $storeLookup->whereKey($storeId)->first();
        }

        $statement = null;
        $days = [];

        if ($store instanceof Store) {
            $statement = $service->findOrCreateForMonth($scopeUser, $store, $year, $month);
            $days = $statement->days()->orderBy('date')->get()->map(
                static fn(StatementDay $day): array => [
                    'id' => $day->getKey(),
                    'date' => $day->getDate(),
                    'cash' => $day->getCash(),
                    'card' => $day->getCard(),
                    'wolt' => $day->getWolt(),
                    'bolt' => $day->getBolt(),
                    'bolt_cash' => $day->getBoltCash(),
                    'foodora' => $day->getFoodora(),
                    'total' => $day->getTotal(),
                ],
            )->all();
        }

        $storesForSelect = \array_map(static fn(Store $store): array => $store->toSelectOption(), $isLimited ? \array_values(\array_filter($stores, static fn(Store $store): bool => $assignedStoreId === $store->getKey())) : $stores);

        return Inertia::render('statements/Index', [
            'statement' => $statement instanceof Statement ? [
                'id' => $statement->getKey(),
                'store_id' => $statement->getStoreId(),
                'year' => $statement->getYear(),
                'month' => $statement->getMonth(),
            ] : null,
            'stores' => $storesForSelect,
            'days' => $days,
            'filters' => [
                'store_id' => $store?->getKey(),
                'year' => $year,
                'month' => $month,
            ],
            'is_admin' => $user->isAdmin(),
        ]);
    }
}
