<?php

declare(strict_types=1);

use App\Domain\Inventory\StockMovementService;
use App\Enums\StockMovementOriginEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Support\Carbon;

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('backfill dry-run is non-mutating and write mode is idempotent', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '5.00']);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 7]);

    $first = InventorySession::factory()->forStore($store)->byUser($user)->create(['counted_at' => '2026-06-01 10:00:00']);
    InventorySessionItem::factory()->create(['session_id' => $first->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);
    $second = InventorySession::factory()->forStore($store)->byUser($user)->create(['counted_at' => '2026-06-08 10:00:00']);
    $secondRow = InventorySessionItem::factory()->create(['session_id' => $second->getKey(), 'item_id' => $item->getKey(), 'quantity' => 7]);

    $this->artisan('stockflow:backfill-inventory-consumption --dry-run')->assertSuccessful();
    \expect($secondRow->fresh()?->getExpectedQuantity())->toBeNull();
    \expect(StockMovement::query()->where('inventory_session_id', $second->getKey())->count())->toBe(0);

    $this->artisan('stockflow:backfill-inventory-consumption')->assertSuccessful();
    $movement = StockMovement::query()->where('inventory_session_id', $second->getKey())->firstOrFail();
    \expect($movement->getOrigin())->toBe(StockMovementOriginEnum::MIGRATION);
    \expect($secondRow->fresh()?->getExpectedQuantity())->toBe(10);
    \expect($secondRow->fresh()?->getQuantityDifference())->toBe(-3);
    \expect(StoreItem::query()->where('store_id', $store->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(7);

    $this->artisan('stockflow:backfill-inventory-consumption')->assertSuccessful();
    \expect(StockMovement::query()->where('inventory_session_id', $second->getKey())->count())->toBe(1);
});

\test('backfill accounts for known transfers and manual consumption between counts', function (): void {
    Carbon::setTestNow('2026-06-01 10:00:00');
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $destination = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);
    $first = InventorySession::factory()->forStore($store)->byUser($user)->create(['counted_at' => '2026-06-01 10:00:00']);
    InventorySessionItem::factory()->create(['session_id' => $first->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);

    Carbon::setTestNow('2026-06-04 10:00:00');
    \app(StockMovementService::class)->createMovement([
        'mode' => 'transfer',
        'source_store_id' => $store->getKey(),
        'store_id' => $destination->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 2]],
    ], $user);
    \app(StockMovementService::class)->createMovement([
        'mode' => 'consumption',
        'store_id' => $store->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
    ], $user);

    $second = InventorySession::factory()->forStore($store)->byUser($user)->create(['counted_at' => '2026-06-08 10:00:00']);
    $secondRow = InventorySessionItem::factory()->create(['session_id' => $second->getKey(), 'item_id' => $item->getKey(), 'quantity' => 6]);

    $this->artisan('stockflow:backfill-inventory-consumption')->assertSuccessful();
    // Expected 10 - transfer 2 - manual consumption 1 = 7; unexplained consumption is only 1.
    \expect($secondRow->fresh()?->getExpectedQuantity())->toBe(7);
    \expect($secondRow->fresh()?->getQuantityDifference())->toBe(-1);
});
