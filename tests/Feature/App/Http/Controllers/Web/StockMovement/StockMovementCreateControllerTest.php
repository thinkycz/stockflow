<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\StockMovementService;

\test('user can create an incoming stock movement to warehouse', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Matcha',
        'purchase_price' => '5.50',
    ]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
    ]);

    $response = $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'store_id' => $warehouse->getKey(),
            'note' => null,
            'items' => [
                [
                    'item_id' => $item->getKey(),
                    'quantity' => 10,
                ],
            ],
        ]);

    $response->assertRedirect();
    $item->refresh();
    \expect($item->getWarehouseQuantity())->toBe(10);
    $movement = StockMovement::query()->where('type', 'incoming')->latest('id')->first();
    \expect($movement)->not->toBeNull();
    \expect($movement->getNumber())->toStartWith('IN-');
    \expect($movement->getTotalQuantity())->toBe(10);
    \expect($movement->getTotalValue())->toBe(55.0);
});

\test('user can create an incoming stock movement to retail store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '2.00',
    ]);
    StoreItem::query()->create([
        'store_id' => $retail->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
    ]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'store_id' => $retail->getKey(),
            'items' => [[
                'item_id' => $item->getKey(),
                'quantity' => 4,
            ]],
        ])
        ->assertRedirect();

    $qty = (int) StoreItem::query()
        ->where('store_id', $retail->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    \expect($qty)->toBe(4);
    $movement = StockMovement::query()->latest('id')->first();
    \expect($movement?->getType()->value)->toBe('incoming');
});

\test('transfer stock movement requires a destination store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);

    $response = $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'source_store_id' => null,
            'store_id' => null,
            'note' => null,
            'items' => [
                [
                    'item_id' => $item->getKey(),
                    'quantity' => 1,
                ],
            ],
        ]);

    $response->assertStatus(422);
    $errors = $response->json('props.errors') ?? $response->json('errors') ?? [];
    \expect($errors)->toHaveKey('store_id');
});

\test('transfer rejects identical source and destination stores', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);

    $response = $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'source_store_id' => $warehouse->getKey(),
            'store_id' => $warehouse->getKey(),
            'items' => [[
                'item_id' => $item->getKey(),
                'quantity' => 1,
            ]],
        ]);

    $response->assertStatus(422);
    $errors = $response->json('props.errors') ?? $response->json('errors') ?? [];
    \expect($errors)->toHaveKey('store_id');
});

\test('outgoing quantity exceeding warehouse stock is rejected', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $response = $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'source_store_id' => $warehouse->getKey(),
            'store_id' => $store->getKey(),
            'note' => null,
            'items' => [
                [
                    'item_id' => $item->getKey(),
                    'quantity' => 99,
                ],
            ],
        ]);

    $response->assertStatus(422);
    $errors = $response->json('props.errors') ?? $response->json('errors') ?? [];
    \expect($errors)->toHaveKey('items');
});

\test('user can create an adjustment stock movement', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '3.00',
    ]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 50,
    ]);

    $response = $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'adjustment',
            'store_id' => $warehouse->getKey(),
            'note' => 'Damaged cups',
            'items' => [
                [
                    'item_id' => $item->getKey(),
                    'quantity_after' => 40,
                    'adjustment_reason' => 'damaged',
                ],
            ],
        ]);

    $response->assertRedirect();
    $item->refresh();
    \expect($item->getWarehouseQuantity())->toBe(40);
    $movement = StockMovement::query()
        ->with(['movementItems'])
        ->where('type', 'adjustment')
        ->latest('id')
        ->first();
    \expect($movement->getNumber())->toStartWith('ADJ-');
    $row = $movement->movementItems->first();
    \expect($row->getQuantityBefore())->toBe(50);
    \expect($row->getQuantityAfter())->toBe(40);
    \expect($row->getQuantityDifference())->toBe(-10);
    \expect($row->getAdjustmentReason()?->value)->toBe('damaged');
});

\test('sequential numbers are generated per user per type per year', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
    ]);

    $service = \app(StockMovementService::class);
    $year = (int) \now()->format('Y');

    $service->createMovement([
        'store_id' => $warehouse->getKey(),
        'note' => null,
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 1,
        ]],
    ], $user);

    $service->createMovement([
        'store_id' => $warehouse->getKey(),
        'note' => null,
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 1,
        ]],
    ], $user);

    $numbers = StockMovement::query()
        ->forUser($user)
        ->where('type', 'incoming')
        ->orderBy('id')
        ->pluck('number')
        ->all();

    \expect($numbers)->toBe([
        \sprintf('IN-%d-0001', $year),
        \sprintf('IN-%d-0002', $year),
    ]);
});

\test('incoming stock can be added to a selected warehouse', function (): void {
    [$user, $defaultWarehouse] = \createIsolatedUserWithWarehouse();
    $secondWarehouse = Store::factory()->warehouse()->create([
        'user_id' => $user->getKey(),
        'name' => 'East Warehouse',
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '2.00',
    ]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'store_id' => $secondWarehouse->getKey(),
            'items' => [[
                'item_id' => $item->getKey(),
                'quantity' => 8,
            ]],
        ])
        ->assertRedirect();

    $defaultQty = (int) StoreItem::query()
        ->where('store_id', $defaultWarehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    $secondQty = (int) StoreItem::query()
        ->where('store_id', $secondWarehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    \expect($defaultQty)->toBe(0);
    \expect($secondQty)->toBe(8);
});

\test('outgoing stock can transfer between two warehouses', function (): void {
    [$user, $sourceWarehouse] = \createIsolatedUserWithWarehouse();
    $destinationWarehouse = Store::factory()->warehouse()->create([
        'user_id' => $user->getKey(),
        'name' => 'Regional Warehouse',
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '4.00',
    ]);
    StoreItem::query()->create([
        'store_id' => $sourceWarehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 15,
    ]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'source_store_id' => $sourceWarehouse->getKey(),
            'store_id' => $destinationWarehouse->getKey(),
            'items' => [[
                'item_id' => $item->getKey(),
                'quantity' => 6,
            ]],
        ])
        ->assertRedirect();

    $sourceQty = (int) StoreItem::query()
        ->where('store_id', $sourceWarehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    $destinationQty = (int) StoreItem::query()
        ->where('store_id', $destinationWarehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    \expect($sourceQty)->toBe(9);
    \expect($destinationQty)->toBe(6);
});

\test('retail to retail transfer is stored as outgoing with transfer label', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $source = Store::factory()->create([
        'user_id' => $user->getKey(),
        'name' => 'Source Branch',
        'is_warehouse' => false,
    ]);
    $destination = Store::factory()->create([
        'user_id' => $user->getKey(),
        'name' => 'Destination Branch',
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '1.00',
    ]);
    StoreItem::query()->create([
        'store_id' => $source->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 12,
    ]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'source_store_id' => $source->getKey(),
            'store_id' => $destination->getKey(),
            'items' => [[
                'item_id' => $item->getKey(),
                'quantity' => 3,
            ]],
        ])
        ->assertRedirect();

    $movement = StockMovement::query()
        ->with('sourceStore')
        ->latest('id')
        ->first();

    \expect($movement?->getType()->value)->toBe('outgoing');
    \expect($movement?->getDisplayLabelKey())->toBe('transfer');

    $sourceQty = (int) StoreItem::query()
        ->where('store_id', $source->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    $destinationQty = (int) StoreItem::query()
        ->where('store_id', $destination->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    \expect($sourceQty)->toBe(9);
    \expect($destinationQty)->toBe(3);
});

\test('transfer mode ignores client-sent type and infers incoming', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '1.00',
    ]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/stock-movements', [
            'mode' => 'transfer',
            'type' => 'outgoing',
            'store_id' => $warehouse->getKey(),
            'items' => [[
                'item_id' => $item->getKey(),
                'quantity' => 2,
            ]],
        ])
        ->assertRedirect();

    $movement = StockMovement::query()->latest('id')->first();
    \expect($movement?->getType()->value)->toBe('incoming');
});
