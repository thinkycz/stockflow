<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;

\test('cannot delete an item with stock movement history', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $movement = StockMovement::factory()->incoming()->create([
        'user_id' => $user->getKey(),
        'created_by' => $user->getKey(),
    ]);

    StockMovementItem::query()->create([
        'stock_movement_id' => $movement->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
        'total' => 10,
        'quantity_before' => 0,
        'quantity_after' => 10,
        'quantity_difference' => 10,
        'adjustment_reason' => null,
    ]);

    $this->be($user, 'users')
        ->delete("/items/{$item->getKey()}")
        ->assertStatus(422);

    \expect(Item::query()->where('id', $item->getKey())->exists())->toBeTrue();
});

\test('can delete an item with no movement history', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $this->be($user, 'users')->delete("/items/{$item->getKey()}")->assertRedirect('/items');
    \expect(Item::query()->where('id', $item->getKey())->exists())->toBeFalse();
});

\test('can delete an item while preserving its completed inventory history', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Historical item',
    ]);
    $session = InventorySession::factory()->forStore($store)->byCreator($user)->create();
    $sessionItem = InventorySessionItem::factory()->create([
        'session_id' => $session->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $this->be($user, 'users')
        ->delete("/items/{$item->getKey()}")
        ->assertRedirect('/items');

    \expect(Item::query()->whereKey($item->getKey())->exists())->toBeFalse();
    \expect(InventorySessionItem::query()->whereKey($sessionItem->getKey())->exists())->toBeTrue();
    \expect($sessionItem->fresh()?->getItem()->getTitle())->toBe('Historical item');
});

\test('deleting an item removes its row from an open inventory draft', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $draft = InventorySession::factory()->forStore($store)->byCreator($user)->create([
        'status' => 'draft',
        'active_store_key' => $store->getKey(),
        'started_at' => \now(),
        'counted_at' => null,
    ]);
    $draftItem = InventorySessionItem::factory()->create([
        'session_id' => $draft->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $this->be($user, 'users')
        ->delete("/items/{$item->getKey()}")
        ->assertRedirect('/items');

    \expect(InventorySessionItem::query()->whereKey($draftItem->getKey())->exists())->toBeFalse();
});
