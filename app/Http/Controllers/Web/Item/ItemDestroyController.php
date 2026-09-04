<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Domain\Catalog\CatalogManagementService;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class ItemDestroyController
{
    /**
     * Delete an item. Blocked when the item is referenced by any stock movement row.
     */
    public function __invoke(Item $item): RedirectResponse
    {
        (new CatalogManagementService())->deleteItem(User::mustAuth(), $item);

        Inertia::flash('success', \__('Item deleted.'));

        return Resolver::resolveRedirector()->route('items.index');
    }
}
