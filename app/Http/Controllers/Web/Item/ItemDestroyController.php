<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\Item;
use App\Models\StockMovementItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class ItemDestroyController
{
    use ValidatesWebRequests;

    /**
     * Delete an item. Blocked when the item is referenced by any stock movement row.
     */
    public function __invoke(Item $item): RedirectResponse
    {
        $hasMovements = StockMovementItem::query()
            ->whereHas('stockMovement', static function (Builder $query) use ($item): void {
                $query->where('user_id', $item->getUserId());
            })
            ->where('item_id', $item->getKey())
            ->exists();

        if ($hasMovements) {
            $this->throwValidationError('item', \__('Cannot delete an item that has stock movement history.'));
        }

        $item->storeItems()->delete();
        $item->delete();

        Inertia::flash('success', \__('Item deleted.'));

        return Resolver::resolveRedirector()->route('items.index');
    }
}
