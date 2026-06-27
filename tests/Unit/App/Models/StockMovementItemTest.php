<?php

declare(strict_types=1);

use App\Enums\AdjustmentReasonEnum;
use App\Models\StockMovementItem;

\test('getStockMovementId returns the stock_movement_id attribute', function (): void {
    $item = StockMovementItem::factory()->make();

    \expect($item->getStockMovementId())->toBeInt();
});

\test('getItemId returns the item_id attribute', function (): void {
    $item = StockMovementItem::factory()->make();

    \expect($item->getItemId())->toBeInt();
});

\test('getQuantity returns an integer or null', function (): void {
    $item = StockMovementItem::factory()->make(['quantity' => 10]);

    \expect($item->getQuantity())->toBe(10);
});

\test('getQuantity returns null when attribute is null', function (): void {
    $item = StockMovementItem::factory()->make(['quantity' => null]);

    \expect($item->getQuantity())->toBeNull();
});

\test('getTotal returns a float', function (): void {
    $item = StockMovementItem::factory()->make(['total' => '100.50']);

    \expect($item->getTotal())->toBe(100.5);
});

\test('getQuantityBefore returns an integer or null', function (): void {
    $item = StockMovementItem::factory()->make(['quantity_before' => 5]);

    \expect($item->getQuantityBefore())->toBe(5);
});

\test('getQuantityBefore returns null when attribute is null', function (): void {
    $item = StockMovementItem::factory()->make(['quantity_before' => null]);

    \expect($item->getQuantityBefore())->toBeNull();
});

\test('getQuantityAfter returns an integer or null', function (): void {
    $item = StockMovementItem::factory()->make(['quantity_after' => 15]);

    \expect($item->getQuantityAfter())->toBe(15);
});

\test('getQuantityDifference returns an integer or null', function (): void {
    $item = StockMovementItem::factory()->make(['quantity_difference' => -3]);

    \expect($item->getQuantityDifference())->toBe(-3);
});

\test('getQuantityDifference returns null when attribute is null', function (): void {
    $item = StockMovementItem::factory()->make(['quantity_difference' => null]);

    \expect($item->getQuantityDifference())->toBeNull();
});

\test('getAdjustmentReason returns an enum when present', function (): void {
    $item = StockMovementItem::factory()->make(['adjustment_reason' => 'damaged']);

    \expect($item->getAdjustmentReason())->toBe(AdjustmentReasonEnum::DAMAGED);
});

\test('getAdjustmentReason returns null when attribute is null', function (): void {
    $item = StockMovementItem::factory()->make(['adjustment_reason' => null]);

    \expect($item->getAdjustmentReason())->toBeNull();
});

\test('scopeSearch is a no-op', function (): void {
    $query = StockMovementItem::query();
    $sqlBefore = $query->toSql();

    StockMovementItem::scopeSearch($query, 'anything');

    \expect($query->toSql())->toBe($sqlBefore);
});

\test('querySelect limits columns', function (): void {
    $query = StockMovementItem::querySelect(StockMovementItem::query());
    $sql = $query->toSql();

    \expect($sql)->toContain('"id"');
    \expect($sql)->toContain('"stock_movement_id"');
    \expect($sql)->toContain('"item_id"');
    \expect($sql)->toContain('"quantity"');
});

\test('timestamps is disabled', function (): void {
    \expect((new StockMovementItem())->timestamps)->toBeFalse();
});

\test('casts returns expected cast definitions', function (): void {
    $item = new StockMovementItem();
    $casts = (new ReflectionMethod($item, 'casts'))->invoke($item);

    \expect($casts)->toHaveKey('quantity', 'integer');
    \expect($casts)->toHaveKey('total', 'decimal:2');
    \expect($casts)->toHaveKey('quantity_before', 'integer');
    \expect($casts)->toHaveKey('quantity_after', 'integer');
    \expect($casts)->toHaveKey('quantity_difference', 'integer');
});
