<?php

declare(strict_types=1);

use App\Models\InventoryCount;
use App\Models\Item;
use App\Models\Store;
use Database\Factories\InventoryCountFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\test('inventory count getters round-trip persisted attributes', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $countedAt = Carbon::parse('2026-06-20 10:00:00');

    $count = Typer::assertInstance(
        InventoryCountFactory::new()
            ->byUser($user)
            ->forStore($store)
            ->forItem($item)
            ->state([
                'quantity' => 42,
                'counted_at' => $countedAt,
                'note' => 'mid-month check',
                'created_by' => $user->getKey(),
            ])
            ->createOne(),
        InventoryCount::class,
    );

    \expect($count->getUserId())->toBe($user->getKey());
    \expect($count->getStoreId())->toBe($store->getKey());
    \expect($count->getItemId())->toBe($item->getKey());
    \expect($count->getQuantity())->toBe(42);
    \expect($count->getNote())->toBe('mid-month check');
    \expect($count->getCreatedBy())->toBe($user->getKey());
    \expect($count->getCountedAt()->timestamp)->toBe($countedAt->timestamp);
    \expect($count->getStore()->getKey())->toBe($store->getKey());
    \expect($count->getItem()->getKey())->toBe($item->getKey());
    \expect($count->getCreator()?->getKey())->toBe($user->getKey());
});

\test('inventory count scopeForStore restricts results to the given store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    InventoryCountFactory::new()
        ->byUser($user)
        ->forStore($store)
        ->forItem($item)
        ->create();

    InventoryCountFactory::new()
        ->byUser($user)
        ->forStore($otherStore)
        ->forItem($item)
        ->create();

    $query = InventoryCount::query();
    InventoryCount::scopeForStore($query, $store->getKey());

    \expect($query->count())->toBe(1);
});

\test('inventory count scopeSince keeps only counts on or after the timestamp', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    InventoryCountFactory::new()
        ->byUser($user)
        ->forStore($store)
        ->forItem($item)
        ->state(['counted_at' => Carbon::parse('2026-06-01 08:00:00')])
        ->create();

    $recent = InventoryCountFactory::new()
        ->byUser($user)
        ->forStore($store)
        ->forItem($item)
        ->state(['counted_at' => Carbon::parse('2026-06-20 08:00:00')])
        ->create();

    $query = InventoryCount::query();
    InventoryCount::scopeSince($query, Carbon::parse('2026-06-15 00:00:00'));

    \expect($query->pluck('id')->all())->toBe([$recent->getKey()]);
});
