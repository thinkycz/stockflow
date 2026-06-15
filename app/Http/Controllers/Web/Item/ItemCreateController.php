<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ItemValidity;
use App\Models\Item;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

class ItemCreateController
{
    use ValidatesWebRequests;

    /**
     * Show the create item form.
     */
    public function create(): Response
    {
        return Inertia::render('items/Create', [
            'units' => ['pcs', 'g', 'kg', 'ml', 'l', 'bag', 'box'],
        ]);
    }

    /**
     * Persist a new item.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = User::mustAuth();
        $itemValidity = ItemValidity::inject($user->getKey());

        $validated = $this->validateRequest($request, [
            'title' => $itemValidity->title()->required()->toArray(),
            'sku' => $itemValidity->sku()->nullable()->toArray(),
            'unit' => $itemValidity->unit()->nullable()->toArray(),
            'purchase_price' => $itemValidity->purchasePrice()->required()->toArray(),
            'description' => $itemValidity->description()->nullable()->toArray(),
        ]);

        $warehouseId = $user->warehouse()->getKey();

        $item = DB::transaction(function () use ($user, $validated, $warehouseId): Item {
            $item = Item::query()->create([
                'user_id' => $user->getKey(),
                'title' => $validated->assertString('title'),
                'sku' => $validated->assertNullableString('sku'),
                'unit' => $validated->assertNullableString('unit'),
                'purchase_price' => $validated->assertString('purchase_price'),
                'description' => $validated->assertNullableString('description'),
            ]);

            StoreItem::query()->create([
                'store_id' => $warehouseId,
                'item_id' => $item->getKey(),
                'quantity' => 0,
            ]);

            return $item;
        });

        Inertia::flash('success', \__('Item created.'));

        return Resolver::resolveRedirector()->route('items.show', $item->getKey());
    }
}
