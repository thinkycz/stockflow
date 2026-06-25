<?php

declare(strict_types=1);

use App\Models\InventorySessionItem;

\test('getItemId returns the item_id attribute', function (): void {
    $item = InventorySessionItem::factory()->make();

    \expect($item->getItemId())->toBeInt();
});

\test('getQuantity returns the quantity attribute', function (): void {
    $item = InventorySessionItem::factory()->make(['quantity' => 10]);

    \expect($item->getQuantity())->toBe(10);
});

\test('getNote returns a string or null', function (): void {
    $item = InventorySessionItem::factory()->make(['note' => 'Check shelf B']);

    \expect($item->getNote())->toBe('Check shelf B');
});

\test('getNote returns null when attribute is null', function (): void {
    $item = InventorySessionItem::factory()->make(['note' => null]);

    \expect($item->getNote())->toBeNull();
});

\test('scopeSearch adds where clause for note', function (): void {
    $query = InventorySessionItem::query();

    InventorySessionItem::scopeSearch($query, 'shelf');

    \expect($query->toSql())->toContain('note');
});

\test('querySelect limits columns', function (): void {
    $query = InventorySessionItem::querySelect(InventorySessionItem::query());
    $sql = $query->toSql();

    \expect($sql)->toContain('"id"');
    \expect($sql)->toContain('"session_id"');
    \expect($sql)->toContain('"item_id"');
    \expect($sql)->toContain('"quantity"');
});

\test('casts returns expected cast definitions', function (): void {
    $item = new InventorySessionItem();
    $casts = (new ReflectionMethod($item, 'casts'))->invoke($item);

    \expect($casts)->toHaveKey('quantity', 'integer');
});
