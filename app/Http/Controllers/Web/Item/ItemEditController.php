<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ItemValidity;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

class ItemEditController
{
    use ValidatesWebRequests;

    /**
     * Show the edit form.
     */
    public function edit(Item $item): Response
    {
        return Inertia::render('items/Edit', [
            'item' => [
                'id' => $item->getKey(),
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'purchase_price' => $item->getPurchasePrice(),
                'description' => $item->getDescription(),
            ],
            'units' => ['pcs', 'g', 'kg', 'ml', 'l', 'bag', 'box'],
        ]);
    }

    /**
     * Persist item updates. Quantity cannot be edited here.
     */
    public function update(Request $request, Item $item): RedirectResponse
    {
        $itemValidity = ItemValidity::inject(User::mustAuth()->getKey());

        $validated = $this->validateRequest($request, [
            'title' => $itemValidity->title()->required()->toArray(),
            'sku' => $itemValidity->sku($item->getKey())->nullable()->toArray(),
            'unit' => $itemValidity->unit()->nullable()->toArray(),
            'purchase_price' => $itemValidity->purchasePrice()->required()->toArray(),
            'description' => $itemValidity->description()->nullable()->toArray(),
        ]);

        $item->update([
            'title' => $validated->assertString('title'),
            'sku' => $validated->assertNullableString('sku'),
            'unit' => $validated->assertNullableString('unit'),
            'purchase_price' => $validated->assertString('purchase_price'),
            'description' => $validated->assertNullableString('description'),
        ]);

        Inertia::flash('success', \__('Item updated.'));

        return Resolver::resolveRedirector()->route('items.show', $item->getKey());
    }
}
