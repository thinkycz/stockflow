<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Typer;

class ItemSearchController
{
    /**
     * Maximum number of items returned per search request.
     */
    public const int TAKE = 20;

    /**
     * Search the authenticated user's items for the combobox.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = User::mustAuth();
        $term = Typer::parseNullableString($request->query('q')) ?? '';
        $term = \trim($term);

        $activeStore = ActiveStoreResolver::resolve($request, $user);
        $activeStoreId = $activeStore instanceof Store ? $activeStore->getKey() : null;

        $items = $term === '' ? [] : $this->search($user, $term, $activeStoreId);

        return new JsonResponse(['items' => $items]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function search(User $user, string $term, int|null $activeStoreId): array
    {
        $itemsQuery = Item::query();
        Item::scopeForUser($itemsQuery, $user);
        $itemsQuery->where(static function (Builder $query) use ($term): void {
            $query->where('title', 'like', '%' . $term . '%')
                ->orWhere('sku', 'like', '%' . $term . '%');
        });

        $items = $itemsQuery
            ->orderBy('title')
            ->limit(self::TAKE)
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $itemIds = $items->map(static fn(Item $item): int => $item->getKey())->all();

        $defaultWarehouse = $user->warehouse();
        $defaultWarehouseId = (string) $defaultWarehouse->getKey();

        $storeQuantitiesByItem = [];
        $storeItemRows = StoreItem::query()
            ->select(['id', 'store_id', 'item_id', 'quantity'])
            ->whereIn('item_id', $itemIds)
            ->whereHas('store', static function (Builder $query) use ($user): void {
                $query->where('user_id', $user->getKey());
            })
            ->get();

        foreach ($storeItemRows as $storeItemRow) {
            $storeQuantitiesByItem[$storeItemRow->getItemId()][(string) $storeItemRow->getStoreId()]
                = (float) $storeItemRow->getQuantity();
        }

        return $items->map(static function (Item $item) use ($storeQuantitiesByItem, $defaultWarehouseId, $activeStoreId): array {
            $byStore = $storeQuantitiesByItem[$item->getKey()] ?? [];

            return [
                'id' => $item->getKey(),
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'warehouse_quantity' => $byStore[$defaultWarehouseId] ?? 0,
                'store_quantity' => $activeStoreId !== null
                    ? ($byStore[(string) $activeStoreId] ?? 0)
                    : null,
                'quantities_by_store' => $byStore,
                'purchase_price' => $item->getPurchasePrice(),
            ];
        })->all();
    }
}
