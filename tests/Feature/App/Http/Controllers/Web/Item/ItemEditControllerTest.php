<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StoreItem;

\test('item edit does not change warehouse quantity', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 42,
    ]);

    $this->be($user, 'users')->put("/items/{$item->getKey()}", [
        'title' => 'Updated Title',
        'sku' => null,
        'unit' => 'g',
        'purchase_price' => '12.00',
        'description' => 'Updated',
    ])->assertRedirect();

    $item->refresh();
    \expect($item->getTitle())->toBe('Updated Title');
    \expect($item->getWarehouseQuantity())->toBe(42);
});
