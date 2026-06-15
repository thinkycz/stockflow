<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StoreValidity;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;
use Thinkycz\LaravelCore\Support\Typer;

class StoreIndexController
{
    use ValidatesWebRequests;

    /**
     * Default page size.
     */
    public const int TAKE = 20;

    /**
     * Show the stores list with per-store totals.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $storeValidity = StoreValidity::inject($user->getKey());

        $validated = $this->validateRequest($request, [
            'search' => $storeValidity->search()->nullable()->toArray(),
        ]);

        $search = $validated->assertNullableString('search') ?? '';

        $baseQuery = Store::query();
        Store::scopeForUser($baseQuery, $user);
        $query = Store::querySelect($baseQuery)->orderBy('name');

        if ($search !== '') {
            Store::scopeSearch($query, $search);
        }

        $stores = $query->get();

        $storeIds = $stores->pluck('id')->all();

        /** @var array<int, stdClass> $metricsRows */
        $metricsRows = $storeIds === []
            ? []
            : DB::table('stock_movements')
                ->where(function (QueryBuilder $q) use ($storeIds): void {
                    $q->whereIn('store_id', $storeIds)
                        ->orWhereIn('source_store_id', $storeIds);
                })
                ->selectRaw('
                    source_store_id,
                    store_id,
                    type,
                    SUM(CASE WHEN type = \'incoming\' THEN total_quantity ELSE 0 END) as total_received_quantity,
                    SUM(CASE WHEN type = \'incoming\' THEN total_value ELSE 0 END) as total_received_value,
                    SUM(CASE WHEN type = \'outgoing\' THEN total_value ELSE 0 END) as total_outgoing_value,
                    COUNT(*) as movements_count
                ')
                ->groupBy('source_store_id', 'store_id', 'type')
                ->get()
                ->all();

        $aggregated = $this->aggregateStoreMetrics($metricsRows);

        $rows = $stores->map(function (Store $store) use ($aggregated): array {
            $metrics = $aggregated[$store->getKey()] ?? [
                'movements_count' => 0,
                'total_received_quantity' => 0,
                'total_received_value' => 0.0,
                'total_outgoing_value' => 0.0,
            ];

            return [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'status' => $store->getStatus()->value,
                'is_warehouse' => $store->isWarehouse(),
                'movements_count' => $metrics['movements_count'],
                'total_received_quantity' => $metrics['total_received_quantity'],
                'total_received_value' => $metrics['total_received_value'],
                'total_outgoing_value' => $metrics['total_outgoing_value'],
            ];
        })->all();

        return Inertia::render('stores/Index', [
            'stores' => $rows,
            'search' => $search,
        ]);
    }

    /**
     * Aggregate the per-store metrics from a single grouped query.
     *
     * Each movement row contributes to one of two buckets per store:
     *  - incoming movements count for the destination store (store_id)
     *  - outgoing movements count for the source store (source_store_id)
     *
     * @param array<int, stdClass> $rows
     *
     * @return array<int, array<string, float|int>>
     */
    private function aggregateStoreMetrics(array $rows): array
    {
        $aggregated = [];

        foreach ($rows as $row) {
            $rowValues = (array) $row;
            $storeId = Typer::parseInt($rowValues['store_id'] ?? null);
            $sourceStoreId = Typer::parseInt($rowValues['source_store_id'] ?? null);
            $type = Typer::assertString($rowValues['type'] ?? null);

            $bucketId = $type === 'incoming' ? $storeId : $sourceStoreId;
            if ($bucketId === 0) {
                continue;
            }

            $bucket = $aggregated[$bucketId] ?? [
                'movements_count' => 0,
                'total_received_quantity' => 0,
                'total_received_value' => 0.0,
                'total_outgoing_value' => 0.0,
            ];
            $bucket['movements_count'] += Typer::parseInt($rowValues['movements_count'] ?? null);
            $bucket['total_received_quantity'] += Typer::parseInt($rowValues['total_received_quantity'] ?? null);
            $bucket['total_received_value'] += Typer::parseFloat($rowValues['total_received_value'] ?? null);
            $bucket['total_outgoing_value'] += Typer::parseFloat($rowValues['total_outgoing_value'] ?? null);
            $aggregated[$bucketId] = $bucket;
        }

        return $aggregated;
    }
}
