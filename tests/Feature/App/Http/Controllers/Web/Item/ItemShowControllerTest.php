<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;

\test('authenticated user can view item details with movement history', function (): void {
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

    $response = $this->be($user, 'users')->get("/items/{$item->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'items/Show');
    $response->assertJsonPath('props.item.id', $item->getKey());
    $response->assertJsonCount(1, 'props.movements');
});
