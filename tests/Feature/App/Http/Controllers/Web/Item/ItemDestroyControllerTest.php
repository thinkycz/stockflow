<?php

declare(strict_types=1);

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
