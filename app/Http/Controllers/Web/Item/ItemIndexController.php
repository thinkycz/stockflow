<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ItemValidity;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class ItemIndexController
{
    use ValidatesWebRequests;

    /**
     * Default page size.
     */
    public const int TAKE = 20;

    /**
     * Show the inventory list.
     *
     * Items are user-scoped (not store-scoped), so all items are listed.
     * When an active store is resolved, each row includes the quantity
     * at that store so the user can see stock levels in context.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $itemValidity = ItemValidity::inject($user->getKey());

        $validated = $this->validateRequest($request, [
            'search' => $itemValidity->search()->nullable()->toArray(),
            'page' => $itemValidity->baseValidity->page()->nullable()->toArray(),
        ]);

        $search = $validated->assertNullableString('search') ?? '';

        $activeStore = ActiveStoreResolver::resolve($request, $user);

        $baseQuery = Item::query();
        Item::scopeForUser($baseQuery, $user);
        $query = Item::querySelect($baseQuery)->orderBy('title');

        if ($search !== '') {
            Item::scopeSearch($query, $search);
        }

        if ($activeStore instanceof Store) {
            $query->addSelect([
                'active_store_quantity' => StoreItem::query()
                    ->select('quantity')
                    ->whereColumn('item_id', 'items.id')
                    ->where('store_id', $activeStore->getKey())
                    ->limit(1),
            ]);
        }

        $paginator = $query->paginate(self::TAKE)->withQueryString();

        $items = $paginator->getCollection()->map(static fn(Item $item): array => [
            'id' => $item->getKey(),
            'title' => $item->getTitle(),
            'sku' => $item->getSku(),
            'unit' => $item->getUnit(),
            'purchase_price' => $item->getPurchasePrice(),
            'store_quantity' => $item->getAttribute('active_store_quantity') !== null
                ? Typer::parseInt($item->getAttribute('active_store_quantity'))
                : null,
        ])->all();

        return Inertia::render('items/Index', [
            'items' => $items,
            'search' => $search,
            'store' => $activeStore instanceof Store
                ? ['id' => $activeStore->getKey(), 'name' => $activeStore->getName()]
                : null,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
