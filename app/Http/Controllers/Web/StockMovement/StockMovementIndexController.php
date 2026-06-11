<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\StockMovement;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StockMovementValidity;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementIndexController
{
    use ValidatesWebRequests;

    /**
     * Default page size.
     */
    public const int TAKE = 20;

    /**
     * Show stock movement list with filters.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $validity = StockMovementValidity::inject($user->getKey());

        $validated = $this->validateRequest($request, [
            'search' => $validity->search()->nullable()->toArray(),
            'type' => $validity->typeFilter()->nullable()->toArray(),
            'store_id' => $validity->storeId()->nullable()->toArray(),
            'date_from' => $validity->dateFrom()->nullable()->toArray(),
            'date_to' => $validity->dateTo()->nullable()->toArray(),
            'page' => $validity->baseValidity->page()->nullable()->toArray(),
        ]);

        $search = $validated->assertNullableString('search') ?? '';
        $type = $validated->assertNullableString('type');
        $storeId = $validated->assertNullableInt('store_id');
        $dateFrom = $validated->assertNullableString('date_from');
        $dateTo = $validated->assertNullableString('date_to');

        $baseQuery = StockMovement::query();
        StockMovement::scopeForUser($baseQuery, $user);
        $query = StockMovement::querySelect($baseQuery)
            ->with(['store', 'sourceStore', 'creator'])
            ->withCount('movementItems')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($search !== '') {
            StockMovement::scopeSearch($query, $search);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        if ($dateFrom !== null) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('created_at', '<=', $dateTo);
        }

        $paginator = $query->paginate(self::TAKE)->withQueryString();

        $movements = $paginator->getCollection()->map(static function (StockMovement $movement): array {
            return [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'display_label_key' => $movement->getDisplayLabelKey(),
                'store_id' => $movement->getStoreId(),
                'store_name' => $movement->getStore()?->getName(),
                'created_at' => $movement->getCreatedAt()->toJSON(),
                'total_quantity' => $movement->getTotalQuantity(),
                'total_value' => $movement->getTotalValue(),
                'items_count' => $movement->getItemsCount(),
                'created_by' => $movement->getCreator()?->getEmail(),
            ];
        })->all();

        $storesQuery = Store::query();
        Store::scopeForUser($storesQuery, $user);
        $stores = Store::querySelect($storesQuery)
            ->orderBy('name')
            ->get()
            ->map(static fn(Store $store): array => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ])
            ->all();

        return Inertia::render('stock-movements/Index', [
            'movements' => $movements,
            'stores' => $stores,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'store_id' => $storeId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
