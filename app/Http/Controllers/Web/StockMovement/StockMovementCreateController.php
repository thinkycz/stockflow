<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\StockMovement;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementClassificationEnum;
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
    public function store(Request $request, StockMovementService $service): RedirectResponse
    {
        $user = User::mustAuth();
        $owner = $user->resolveScopeUser();
        $validity = StockMovementValidity::inject($owner->getKey());
        $mode = $request->input('mode');
        $isAdjustment = $mode === 'adjustment';
        $isConsumption = $mode === 'consumption';
        $isIncoming = $mode === 'incoming';

        $rules = [
            'note' => $validity->note()->nullable()->toArray(),
            'occurred_at' => $user->isAdmin()
                ? ['nullable', 'date', 'before_or_equal:now']
                : ['prohibited'],
            'items' => $validity->items()->required()->toArray(),
            'items.*.item_id' => $validity->rowItemId()->required()->toArray(),
        ];

        if ($isAdjustment) {
            $rules['mode'] = $validity->baseValidity->mode(['adjustment'])->nullable()->toArray();
            $rules['store_id'] = $validity->activeStoreId()->required()->toArray();
            $rules['items.*.quantity_after'] = $validity->rowQuantityAfter()->required()->toArray();
            $rules['items.*.adjustment_reason'] = $validity->rowAdjustmentReason()->required()->toArray();
        } elseif ($isConsumption) {
            $rules['mode'] = $validity->baseValidity->mode(['consumption'])->required()->toArray();
            $rules['store_id'] = $validity->activeStoreId()->required()->toArray();
            $rules['items.*.quantity'] = $validity->rowQuantity()->required()->toArray();
        } elseif ($isIncoming) {
            $rules['mode'] = $validity->baseValidity->mode(['incoming'])->required()->toArray();
            $rules['store_id'] = $validity->activeStoreId()->required()->toArray();
            $rules['items.*.quantity'] = $validity->rowQuantity()->required()->toArray();
        } else {
            $rules['mode'] = $validity->baseValidity->mode(['transfer'])->nullable()->toArray();
            $rules['source_store_id'] = $validity->activeStoreId()->nullable()->toArray();
            $rules['store_id'] = $validity->activeStoreId()->required()->toArray();
            $rules['items.*.quantity'] = $validity->rowQuantity()->required()->toArray();
        }

        $validated = $this->validateRequest($request, $rules);

        $payload = [
            'mode' => $isAdjustment ? 'adjustment' : ($isConsumption ? 'consumption' : ($isIncoming ? 'incoming' : 'transfer')),
            'store_id' => Typer::parseNullableInt($validated->mixed('store_id')),
            'source_store_id' => Typer::parseNullableInt($validated->mixed('source_store_id')),
            'note' => $validated->assertNullableString('note'),
            'occurred_at' => $validated->assertNullableString('occurred_at'),
            'items' => $validated->assertArray('items'),
        ];

        $movement = $service->createMovement($payload, $user);

        Inertia::flash('success', \__('Stock movement created.'));

        if (!$user->isAdmin()) {
            return Resolver::resolveRedirector()->route('dashboard');
        }

        return Resolver::resolveRedirector()->route('stock-movements.show', $movement->getKey());
    }
}
