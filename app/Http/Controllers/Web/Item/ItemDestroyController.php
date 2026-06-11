<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Models\Item;
use App\Models\StockMovementItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;

class ItemDestroyController
{
    /**
     * Delete an item. Blocked when the item is referenced by any stock movement row.
     */
    public function __invoke(Request $request, Item $item): RedirectResponse
    {
        $hasMovements = StockMovementItem::query()
            ->whereHas('stockMovement', static function ($query) use ($item): void {
                $query->forUser($item->getUserId());
            })
            ->where('item_id', $item->getKey())
            ->exists();

        if ($hasMovements) {
            $thrower = new Thrower(Resolver::resolveValidatorFactory()->make([], []));
            $thrower->message('item', \__('Cannot delete an item that has stock movement history.'));
            $thrower->throw();
        }

        $item->delete();

        Inertia::flash('success', \__('Item deleted.'));

        return Resolver::resolveRedirector()->to('/items');
    }
}
