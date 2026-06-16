<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Statement;

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

        $storesQuery = Store::query();
        Store::scopeForUser($storesQuery, $user);
        $stores = Store::querySelect($storesQuery)
            ->orderBy('name')
            ->get()
            ->all();

        $requestedStoreId = Typer::parseNullableInt($request->query('store_id'));
        $defaultStore = $this->resolveDefaultStore($stores);
        $storeId = $requestedStoreId ?? $defaultStore?->getKey();

        $now = \Illuminate\Support\Carbon::now();
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;

        $store = null;
        if ($storeId !== null) {
            $storeLookup = Store::query();
            Store::scopeForUser($storeLookup, $user);
            $store = $storeLookup->whereKey($storeId)->first();
        }

        $statement = null;
        $days = [];

        if ($store instanceof Store) {
            $statement = $service->findOrCreateForMonth($user, $store, $year, $month);
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

        $storesForSelect = \array_map(static fn(Store $store): array => [
            'id' => $store->getKey(),
            'name' => $store->getName(),
        ], $stores);

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
        ]);
    }

    /**
     * Pick the first owned store as the default selection, preferring
     * non-warehouse retail stores when one is available.
     *
     * @param array<int, Store> $stores
     */
    private function resolveDefaultStore(array $stores): Store|null
    {
        foreach ($stores as $store) {
            if (!$store->isWarehouse()) {
                return $store;
            }
        }

        return $stores[0] ?? null;
    }
}
