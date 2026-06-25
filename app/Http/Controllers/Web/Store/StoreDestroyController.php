<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\StockMovement;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class StoreDestroyController
{
    use ValidatesWebRequests;

    /**
     * Delete a store. Blocked when the store has inventory or stock movement history.
     */
    public function __invoke(Store $store): RedirectResponse
    {
        $hasInventory = $store->storeItems()->exists();

        $hasMovements = StockMovement::query()
            ->where('user_id', $store->getUserId())
            ->where(static function (Builder $query) use ($store): void {
                $query->where('store_id', $store->getKey())
                    ->orWhere('source_store_id', $store->getKey());
            })
            ->exists();

        if ($hasInventory || $hasMovements) {
            $this->throwValidationError('store', \__('Cannot delete a store that has inventory or stock movement history.'));
        }

        $store->delete();

        Inertia::flash('success', \__('Store deleted.'));

        return Resolver::resolveRedirector()->route('stores.index');
    }
}
