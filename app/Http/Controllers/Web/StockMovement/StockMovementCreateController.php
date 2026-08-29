<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\StockMovement;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementClassificationEnum;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Operations\Inventory\CreateStockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class StockMovementCreateController
{
    /**
     * Show the dynamic create-movement form.
     */
    public function create(Request $request): Response
    {
        $user = User::mustAuth();
        $requestedMode = $request->query('mode');
        $mode = !$user->isAdmin()
            ? ($requestedMode === 'incoming' ? 'incoming' : 'consumption')
            : (\in_array($requestedMode, ['adjustment', 'consumption'], true) ? $requestedMode : 'transfer');
        $owner = $user->resolveScopeUser();

        $storesQuery = Store::query();
        Store::scopeForUser($storesQuery, $owner);
        Store::scopeActive($storesQuery);
        if (!$user->isAdmin()) {
            $storesQuery->whereKey($user->getAssignedStoreId());
        }
        $stores = Store::querySelect($storesQuery)
            ->orderBy('name')
            ->get()
            ->map(static fn(Store $store): array => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'is_warehouse' => $store->isWarehouse(),
            ])
            ->all();

        /** @var array<int, array<string, float>> $storeQuantitiesByItem */
        $storeQuantitiesByItem = [];
        $storeItemRows = StoreItem::query()
            ->select(['id', 'store_id', 'item_id', 'quantity'])
            ->whereHas('store', static function (Builder $query) use ($owner): void {
                $query->where('user_id', $owner->getKey());
            })
            ->get();

        foreach ($storeItemRows as $storeItemRow) {
            $storeQuantitiesByItem[$storeItemRow->getItemId()][(string) $storeItemRow->getStoreId()]
                = $storeItemRow->getQuantity();
        }

        $defaultWarehouse = $owner->warehouse();
        $defaultItemId = Typer::parseNullableInt($request->query('item_id'));

        $items = [];
        if ($defaultItemId !== null) {
            $defaultItemQuery = Item::query();
            Item::scopeForUser($defaultItemQuery, $owner);
            $defaultItem = $defaultItemQuery->whereKey($defaultItemId)->first();

            if ($defaultItem instanceof Item) {
                $byStore = $storeQuantitiesByItem[$defaultItem->getKey()] ?? [];
                $items = [[
                    'id' => $defaultItem->getKey(),
                    'title' => $defaultItem->getTitle(),
                    'sku' => $defaultItem->getSku(),
                    'unit' => $defaultItem->getUnit(),
                    'warehouse_quantity' => (float) ($byStore[(string) $defaultWarehouse->getKey()] ?? 0),
                    'quantities_by_store' => $byStore,
                    'purchase_price' => $defaultItem->getPurchasePrice(),
                ]];
            }
        }

        return Inertia::render('stock-movements/Create', [
            'stores' => $stores,
            'items' => $items,
            'reasons' => \array_map(
                static fn(AdjustmentReasonEnum $reason): string => $reason->value,
                AdjustmentReasonEnum::cases(),
            ),
            'classifications' => \array_map(
                static fn(StockMovementClassificationEnum $classification): string => $classification->value,
                StockMovementClassificationEnum::cases(),
            ),
            'defaults' => [
                'mode' => $mode,
                'item_id' => $request->query('item_id'),
                'warehouse_id' => $defaultWarehouse->getKey(),
            ],
            'is_admin' => $user->isAdmin(),
        ]);
    }

    /**
     * Validate and persist a new stock movement.
     */
    public function store(Request $request, CreateStockMovement $command): RedirectResponse
    {
        $user = User::mustAuth();
        $movement = $command->execute($user, Typer::assertStringKeyArray($request->all()));

        Inertia::flash('success', \__('Stock movement created.'));

        if (!$user->isAdmin()) {
            return Resolver::resolveRedirector()->route('dashboard');
        }

        return Resolver::resolveRedirector()->route('stock-movements.show', $movement->getKey());
    }
}
