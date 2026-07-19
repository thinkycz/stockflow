<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StoreValidity;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
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
    public function __invoke(Request $request, InventorySessionService $inventoryService): Response
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

        $paginator = $query->paginate(self::TAKE)->withQueryString();
        $stores = $paginator->getCollection();

        $storeIds = $stores->pluck('id')->all();

        $allStoreItems = StoreItem::query()->whereIn('store_id', $storeIds)->with('item')->get();
        $lastInventories = DB::table('inventory_sessions')
            ->whereIn('store_id', $storeIds)
            ->where('status', 'closed')
            ->groupBy('store_id')
            ->pluck(DB::raw('MAX(counted_at)'), 'store_id');

        $rows = $stores->map(function (Store $store) use ($allStoreItems, $inventoryService, $lastInventories): array {
            /** @var Collection<array-key, StoreItem> $storeItems */
            $storeItems = new Collection($allStoreItems->where('store_id', $store->getKey())->values()->all());
            $predictions = $inventoryService->predictionsForStore($store, $storeItems);
            $inventoryValue = 0.0;
            $out = 0;
            $risk = 0;
            foreach ($storeItems as $storeItem) {
                $inventoryValue += $storeItem->getQuantity() * $storeItem->getItem()->getPurchasePrice();
                if ($storeItem->getQuantity() <= 0) {
                    ++$out;
                }
                if ($predictions[$storeItem->getItemId()]['status'] === InventorySessionService::STATUS_SOON) {
                    ++$risk;
                }
            }

            return [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'status' => $store->getStatus()->value,
                'is_warehouse' => $store->isWarehouse(),
                'inventory_value' => \round($inventoryValue, 2),
                'sku_count' => $storeItems->count(),
                'out_of_stock' => $out,
                'risk_count' => $risk,
                'last_inventory_at' => Typer::parseNullableString($lastInventories->get($store->getKey())),
            ];
        })->all();

        return Inertia::render('stores/Index', [
            'stores' => $rows,
            'search' => $search,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
