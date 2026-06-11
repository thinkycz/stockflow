<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\StockMovement;

use App\Enums\AdjustmentReasonEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StockMovementValidity;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class StockMovementCreateController
{
    use ValidatesWebRequests;

    /**
     * Show the dynamic create-movement form.
     */
    public function create(Request $request): Response
    {
        $user = User::mustAuth();
        $mode = $request->query('mode') === 'adjustment' ? 'adjustment' : 'transfer';

        $storesQuery = Store::query();
        Store::scopeForUser($storesQuery, $user);
        Store::scopeActive($storesQuery);
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
            ->whereHas('store', static function (Builder $query) use ($user): void {
                $query->where('user_id', $user->getKey());
            })
            ->get();

        foreach ($storeItemRows as $storeItemRow) {
            $storeQuantitiesByItem[$storeItemRow->getItemId()][(string) $storeItemRow->getStoreId()]
                = $storeItemRow->getQuantity();
        }

        $defaultWarehouse = $user->warehouse();

        $itemsQuery = Item::query();
        Item::scopeForUser($itemsQuery, $user);
        $items = Item::querySelect($itemsQuery)
            ->orderBy('title')
            ->get()
            ->map(static function (Item $item) use ($defaultWarehouse, $storeQuantitiesByItem): array {
                $byStore = $storeQuantitiesByItem[$item->getKey()] ?? [];

                return [
                    'id' => $item->getKey(),
                    'title' => $item->getTitle(),
                    'sku' => $item->getSku(),
                    'unit' => $item->getUnit(),
                    'warehouse_quantity' => (float) ($byStore[(string) $defaultWarehouse->getKey()] ?? 0),
                    'quantities_by_store' => $byStore,
                    'purchase_price' => $item->getPurchasePrice(),
                ];
            })
            ->all();

        return Inertia::render('stock-movements/Create', [
            'stores' => $stores,
            'items' => $items,
            'reasons' => \array_map(
                static fn(AdjustmentReasonEnum $reason): string => $reason->value,
                AdjustmentReasonEnum::cases(),
            ),
            'defaults' => [
                'mode' => $mode,
                'item_id' => $request->query('item_id'),
                'warehouse_id' => $defaultWarehouse->getKey(),
            ],
        ]);
    }

    /**
     * Validate and persist a new stock movement.
     */
    public function store(Request $request, StockMovementService $service): RedirectResponse
    {
        $user = User::mustAuth();
        $validity = StockMovementValidity::inject($user->getKey());
        $mode = $request->input('mode');
        $isAdjustment = $mode === 'adjustment';

        $rules = [
            'note' => $validity->note()->nullable()->toArray(),
            'items' => $validity->items()->required()->toArray(),
            'items.*.item_id' => $validity->rowItemId()->required()->toArray(),
        ];

        if ($isAdjustment) {
            $rules['mode'] = $validity->baseValidity->mode(['adjustment'])->nullable()->toArray();
            $rules['store_id'] = $validity->storeId()->required()->toArray();
            $rules['items.*.quantity_after'] = $validity->rowQuantityAfter()->required()->toArray();
            $rules['items.*.adjustment_reason'] = $validity->rowAdjustmentReason()->required()->toArray();
        } else {
            $rules['mode'] = $validity->baseValidity->mode(['transfer'])->nullable()->toArray();
            $rules['source_store_id'] = $validity->storeId()->nullable()->toArray();
            $rules['store_id'] = $validity->storeId()->required()->toArray();
            $rules['items.*.quantity'] = $validity->rowQuantity()->required()->toArray();
        }

        $validated = $this->validateRequest($request, $rules);

        $payload = [
            'mode' => $isAdjustment ? 'adjustment' : 'transfer',
            'store_id' => Typer::parseNullableInt($validated->mixed('store_id')),
            'source_store_id' => Typer::parseNullableInt($validated->mixed('source_store_id')),
            'note' => $validated->assertNullableString('note'),
            'items' => $validated->assertArray('items'),
        ];

        $movement = $service->createMovement($payload, $user);

        Inertia::flash('success', \__('Stock movement created.'));

        return Resolver::resolveRedirector()->to('/stock-movements/' . $movement->getKey());
    }
}
