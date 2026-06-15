<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use Database\Factories\StoreFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('getters round-trip the persisted attributes', function (): void {
    $store = Typer::assertInstance(StoreFactory::new()->createOne([
        'name' => 'Brno pobočka',
        'address' => 'Hlavní 1, Brno',
        'status' => StoreStatusEnum::ACTIVE->value,
        'notes' => 'Open Mon-Fri',
        'is_warehouse' => false,
    ]), Store::class);

    \expect($store->getName())->toBe('Brno pobočka');
    \expect($store->getAddress())->toBe('Hlavní 1, Brno');
    \expect($store->getStatus())->toBe(StoreStatusEnum::ACTIVE);
    \expect($store->getNotes())->toBe('Open Mon-Fri');
    \expect($store->isWarehouse())->toBeFalse();
    \expect($store->getUserId())->toBeInt();
});

\test('warehouse store exposes its is_warehouse flag and user id', function (): void {
    $store = Typer::assertInstance(
        StoreFactory::new()->warehouse()->createOne(['name' => 'Hlavní sklad']),
        Store::class,
    );

    \expect($store->isWarehouse())->toBeTrue();
    \expect($store->getUserId())->toBe($store->getUserId());
});

\test('scopeSearch matches by name and address', function (): void {
    StoreFactory::new()->createOne(['name' => 'Praha centrála', 'address' => 'Václavské náměstí']);
    StoreFactory::new()->createOne(['name' => 'Brno pobočka', 'address' => 'Hlavní 1']);

    $prague = Store::query();
    Store::scopeSearch($prague, 'Praha');
    \expect($prague->count())->toBe(1);

    $hlavni = Store::query();
    Store::scopeSearch($hlavni, 'Hlavní');
    \expect($hlavni->count())->toBe(1);

    $none = Store::query();
    Store::scopeSearch($none, 'nope');
    \expect($none->count())->toBe(0);
});

\test('scopeWarehouse and scopeRetail filter by is_warehouse', function (): void {
    StoreFactory::new()->warehouse()->createOne(['name' => 'WH1']);
    StoreFactory::new()->createOne(['name' => 'Retail A']);
    StoreFactory::new()->createOne(['name' => 'Retail B']);

    $warehouses = Store::query();
    Store::scopeWarehouse($warehouses);
    \expect($warehouses->count())->toBe(1);

    $retail = Store::query();
    Store::scopeRetail($retail);
    \expect($retail->count())->toBe(2);
});

\test('getStockMovements and getStoreItems return eager-loaded relations', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    Typer::assertInstance(
        StoreItem::factory()->create(['store_id' => $warehouse->getKey()]),
        StoreItem::class,
    );
    StockMovement::factory()->incoming()->createOne([
        'user_id' => $user->getKey(),
        'store_id' => $warehouse->getKey(),
    ]);
    $warehouse->refresh();

    \expect($warehouse->getStoreItems())->toHaveCount(1);
    \expect($warehouse->getStoreItems()->first())->toBeInstanceOf(StoreItem::class);
    \expect($warehouse->getStockMovements())->toHaveCount(1);
    \expect($warehouse->getStockMovements()->first())->toBeInstanceOf(StockMovement::class);
});
