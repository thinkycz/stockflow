<?php

declare(strict_types=1);

use App\Enums\ItemStockStatusEnum;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use Database\Factories\ItemFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('getters round-trip the persisted attributes', function (): void {
    $item = Typer::assertInstance(
        ItemFactory::new()->createOne([
            'title' => 'Espresso cup 8oz',
            'sku' => 'BUB-CUP-008',
            'unit' => 'pcs',
            'purchase_price' => '12.50',
            'description' => '8oz ceramic cup',
        ]),
        Item::class,
    );

    \expect($item->getTitle())->toBe('Espresso cup 8oz');
    \expect($item->getSku())->toBe('BUB-CUP-008');
    \expect($item->getUnit())->toBe('pcs');
    \expect($item->getPurchasePrice())->toBe(12.5);
    \expect($item->getDescription())->toBe('8oz ceramic cup');
    \expect($item->getUserId())->toBeInt();
});

\test('getters return null for nullable fields that were never set', function (): void {
    $item = Typer::assertInstance(
        ItemFactory::new()->createOne([
            'title' => 'Unbranded',
            'sku' => null,
            'unit' => null,
            'description' => null,
        ]),
        Item::class,
    );

    \expect($item->getSku())->toBeNull();
    \expect($item->getUnit())->toBeNull();
    \expect($item->getDescription())->toBeNull();
});

\test('scopeSearch matches by title and sku', function (): void {
    ItemFactory::new()->createOne(['title' => 'Espresso cup 8oz', 'sku' => 'CUP-008']);
    ItemFactory::new()->createOne(['title' => 'Straw 6mm', 'sku' => 'STR-006']);
    ItemFactory::new()->createOne(['title' => 'Lid flat', 'sku' => 'LID-FLT']);

    $cup = Item::query();
    Item::scopeSearch($cup, 'cup');
    \expect($cup->count())->toBe(1);

    $str = Item::query();
    Item::scopeSearch($str, 'STR');
    \expect($str->count())->toBe(1);

    $none = Item::query();
    Item::scopeSearch($none, 'nope');
    \expect($none->count())->toBe(0);
});

\test('getWarehouseQuantity and getTotalQuantity sum the store_items rows', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Typer::assertInstance(ItemFactory::new()->createOne(['user_id' => $user->getKey()]), Item::class);

    StoreItem::factory()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 42,
    ]);

    \expect($item->getWarehouseQuantity())->toBe(42);
    \expect($item->getTotalQuantity())->toBe(42);
    \expect($item->getTotalValue())->toBe(42.0 * $item->getPurchasePrice());
});

\test('getStockStatus reflects warehouse quantity', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Typer::assertInstance(
        ItemFactory::new()->createOne(['user_id' => $user->getKey()]),
        Item::class,
    );

    \expect($item->getStockStatus())->toBe(ItemStockStatusEnum::OUT_OF_STOCK);

    StoreItem::factory()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 3,
    ]);
    $item->refresh();

    \expect($item->getStockStatus())->toBe(ItemStockStatusEnum::LOW_STOCK);

    // The cached warehouse_quantity_sum attribute (populated by
    // querySelect) is what getStockStatus reads when the relation
    // is loaded. Setting it directly avoids having to thread a real
    // row through the Eloquent collection type check.
    $item->setAttribute('warehouse_quantity_sum', 20);
    \expect($item->getStockStatus())->toBe(ItemStockStatusEnum::IN_STOCK);
});

\test('relationships point at the expected related models', function (): void {
    $item = Typer::assertInstance(ItemFactory::new()->createOne(), Item::class);

    \expect($item->stockMovementItems())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    \expect($item->stockMovements())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    \expect($item->storeItems())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    \expect($item->stores())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

\test('eager-loaded relations come back through the get* helpers', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Typer::assertInstance(
        ItemFactory::new()->createOne(['user_id' => $user->getKey()]),
        Item::class,
    );
    StoreItem::factory()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);
    $item->refresh();

    \expect($item->getStoreItems())->toHaveCount(1);
    \expect($item->getStoreItems()->first())->toBeInstanceOf(StoreItem::class);
    \expect($item->getStores())->toHaveCount(1);
    \expect($item->getStores()->first())->toBeInstanceOf(Store::class);
});
