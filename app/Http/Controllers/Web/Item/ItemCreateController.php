<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Domain\Catalog\CatalogManagementService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ItemValidity;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $item = (new CatalogManagementService())->createItem(
            $user,
            $validated->assertString('title'),
            $validated->assertNullableString('sku'),
            $validated->assertNullableString('unit'),
            $validated->assertString('purchase_price'),
            $validated->assertNullableString('description'),
        );

        Inertia::flash('success', \__('Item created.'));

        return Resolver::resolveRedirector()->route('items.show', $item->getKey());
    }
}
