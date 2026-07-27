<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\Store;

\test('getName returns the name attribute', function (): void {
    $store = Store::factory()->make(['name' => 'Warehouse A']);

    \expect($store->getName())->toBe('Warehouse A');
});

\test('getAddress returns a string or null', function (): void {
    $store = Store::factory()->make(['address' => '123 Main St']);

    \expect($store->getAddress())->toBe('123 Main St');
});

\test('getAddress returns null when attribute is null', function (): void {
    $store = Store::factory()->make(['address' => null]);

    \expect($store->getAddress())->toBeNull();
});

\test('getStatus returns a StoreStatusEnum', function (): void {
    $store = Store::factory()->make(['status' => 'active']);

    \expect($store->getStatus())->toBe(StoreStatusEnum::ACTIVE);
});

\test('getStatus handles inactive status', function (): void {
    $store = Store::factory()->make(['status' => 'inactive']);

    \expect($store->getStatus())->toBe(StoreStatusEnum::INACTIVE);
});

\test('isWarehouse returns true for warehouse stores', function (): void {
    $store = Store::factory()->warehouse()->make();

    \expect($store->isWarehouse())->toBeTrue();
});

\test('isWarehouse returns false for retail stores', function (): void {
    $store = Store::factory()->make(['is_warehouse' => false]);

    \expect($store->isWarehouse())->toBeFalse();
});

\test('getNotes returns a string or null', function (): void {
    $store = Store::factory()->make(['notes' => 'Some notes']);

    \expect($store->getNotes())->toBe('Some notes');
});

\test('getNotes returns null when attribute is null', function (): void {
    $store = Store::factory()->make(['notes' => null]);

    \expect($store->getNotes())->toBeNull();
});

\test('getUserId returns the user_id attribute', function (): void {
    $store = Store::factory()->make();

    \expect($store->getUserId())->toBeInt();
});

\test('scopeSearch adds where clauses for name and address', function (): void {
    $query = Store::query();

    Store::scopeSearch($query, 'test');
    $sql = $query->toSql();

    \expect($sql)->toContain('name');
    \expect($sql)->toContain('address');
});

\test('scopeActive filters by active status', function (): void {
    $query = Store::query();

    Store::scopeActive($query);

    \expect($query->toSql())->toContain('status');
});

\test('scopeWarehouse filters by is_warehouse true', function (): void {
    $query = Store::query();

    Store::scopeWarehouse($query);

    \expect($query->toSql())->toContain('is_warehouse');
});

\test('scopeRetail filters by is_warehouse false', function (): void {
    $query = Store::query();

    Store::scopeRetail($query);

    \expect($query->toSql())->toContain('is_warehouse');
});

\test('querySelect limits columns', function (): void {
    $query = Store::querySelect(Store::query());
    $sql = $query->toSql();

    \expect($sql)->toContain('"id"');
    \expect($sql)->toContain('"name"');
    \expect($sql)->toContain('"status"');
    \expect($sql)->toContain('"is_warehouse"');
});

\test('casts returns expected cast definitions', function (): void {
    $store = new Store();
    $casts = (new ReflectionMethod($store, 'casts'))->invoke($store);

    \expect($casts)->toHaveKey('status', StoreStatusEnum::class);
    \expect($casts)->toHaveKey('is_warehouse', 'boolean');
});
