<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Support\Facades\DB;

\test('inventory screens load a whole branch without per-item queries', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $user->update(['active_store_id' => $store->getKey()]);

    Item::factory()->count(30)->create(['user_id' => $user->getKey()])
        ->each(static function (Item $item) use ($store): void {
            StoreItem::factory()->create([
                'store_id' => $store->getKey(),
                'item_id' => $item->getKey(),
                'quantity' => '10.000',
            ]);
        });

    $urls = [
        \route('dashboard'),
        \route('reports.index', ['store_id' => $store->getKey()]),
        \route('inventory-counts.index', ['store_id' => $store->getKey()]),
        \route('stores.show', $store),
    ];

    foreach ($urls as $url) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->be($user, 'users')->get($url, $this->inertiaHeaders())->assertOk();

        $count = \count(DB::getQueryLog());
        DB::disableQueryLog();
        \expect($count)->toBeLessThanOrEqual(
            40,
            "{$url} executed {$count} queries for 30 inventory rows.",
        );
    }
});
