<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StoreItem;
use Database\Factories\StoreItemFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('getters round-trip the persisted attributes', function (): void {
    $row = Typer::assertInstance(
        StoreItemFactory::new()->createOne(['quantity' => 17]),
        StoreItem::class,
    );

    \expect($row->getStoreId())->toBeInt();
    \expect($row->getItemId())->toBeInt();
    \expect($row->getQuantity())->toBe(17);
});

\test('scopeSearch matches by the related item title and sku', function (): void {
    $itemA = Item::factory()->createOne(['title' => 'Espresso cup', 'sku' => 'CUP-A']);
    $itemB = Item::factory()->createOne(['title' => 'Straw 6mm', 'sku' => 'STR-B']);

    StoreItemFactory::new()->createOne(['item_id' => $itemA->getKey()]);
    StoreItemFactory::new()->createOne(['item_id' => $itemB->getKey()]);

    \expect(StoreItem::query()->where(fn($q) => StoreItem::scopeSearch(...)($q, 'Espresso'))->count())->toBe(1);
    \expect(StoreItem::query()->where(fn($q) => StoreItem::scopeSearch(...)($q, 'STR'))->count())->toBe(1);
    \expect(StoreItem::query()->where(fn($q) => StoreItem::scopeSearch(...)($q, 'nope'))->count())->toBe(0);
});

\test('relationships point at the expected related models', function (): void {
    $row = Typer::assertInstance(StoreItemFactory::new()->createOne(), StoreItem::class);

    \expect($row->store())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    \expect($row->item())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

\test('eager-loaded relations come back through the get* helpers', function (): void {
    $row = Typer::assertInstance(StoreItemFactory::new()->createOne(), StoreItem::class);
    $store = $row->store;
    $item = $row->item;
    $row->setRelation('store', $store);
    $row->setRelation('item', $item);

    \expect($row->getStore())->toBe($store);
    \expect($row->getItem())->toBe($item);
});
