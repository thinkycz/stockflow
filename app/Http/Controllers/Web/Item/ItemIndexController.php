<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ItemValidity;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemIndexController
{
    use ValidatesWebRequests;

    /**
     * Default page size.
     */
    public const int TAKE = 20;

    /**
     * Show the inventory list.
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

        $query = Item::querySelect(Item::query()->forUser($user))->orderBy('title');

        if ($search !== '') {
            $query->search($search);
        }

        $paginator = $query->paginate(self::TAKE)->withQueryString();

        $items = $paginator->getCollection()->map(static fn(Item $item): array => [
            'id' => $item->getKey(),
            'title' => $item->getTitle(),
            'sku' => $item->getSku(),
            'unit' => $item->getUnit(),
            'warehouse_quantity' => $item->getWarehouseQuantity(),
            'total_quantity' => $item->getTotalQuantity(),
            'purchase_price' => $item->getPurchasePrice(),
            'total_value' => $item->getTotalValue(),
            'status' => $item->getStockStatus()->value,
        ])->all();

        return Inertia::render('items/Index', [
            'items' => $items,
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
