<?php

declare(strict_types=1);

use App\Domain\Inventory\StockMovementService;
use App\Models\Item;
use App\Models\StockMovementItem;
use App\Models\StoreItem;
use Illuminate\Support\Facades\DB;

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

\test('item with missing creation timestamp can be viewed after its price is edited', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    DB::table('items')->where('id', $item->getKey())->update(['created_at' => null]);

    $this->be($user, 'users')->put("/items/{$item->getKey()}", [
        'title' => $item->getTitle(),
        'sku' => $item->getSku(),
        'unit' => $item->getUnit(),
        'purchase_price' => '24.00',
        'description' => $item->getDescription(),
    ])->assertRedirect("/items/{$item->getKey()}");

    $this->get("/items/{$item->getKey()}", $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.item.purchase_price', 24);
});

\test('item price edit only affects newly created stock movements', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '5.00',
    ]);
    $service = \app(StockMovementService::class);

    $pastMovement = $service->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 2]],
    ], $user);

    $this->be($user, 'users')->put("/items/{$item->getKey()}", [
        'title' => $item->getTitle(),
        'sku' => $item->getSku(),
        'unit' => $item->getUnit(),
        'purchase_price' => '8.00',
        'description' => $item->getDescription(),
    ])->assertRedirect("/items/{$item->getKey()}");

    $pastRow = StockMovementItem::query()->where('stock_movement_id', $pastMovement->getKey())->firstOrFail();
    \expect($pastRow->getUnitCost())->toBe(5.0)
        ->and($pastRow->getTotal())->toBe(10.0)
        ->and($pastMovement->fresh()?->getTotalValue())->toBe(10.0);

    $newMovement = $service->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 3]],
    ], $user);
    $newRow = StockMovementItem::query()->where('stock_movement_id', $newMovement->getKey())->firstOrFail();

    \expect($newRow->getUnitCost())->toBe(8.0)
        ->and($newRow->getTotal())->toBe(24.0)
        ->and($newMovement->getTotalValue())->toBe(24.0);
});
