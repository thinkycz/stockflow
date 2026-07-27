<?php

declare(strict_types=1);

use App\Enums\ItemStockStatusEnum;
use App\Models\Item;

\test('values returns all case values', function (): void {
    \expect(ItemStockStatusEnum::values())->toBe(['in_stock', 'low_stock', 'out_of_stock']);
});

\test('fromQuantity returns OUT_OF_STOCK for zero', function (): void {
    \expect(ItemStockStatusEnum::fromQuantity(0))->toBe(ItemStockStatusEnum::OUT_OF_STOCK);
});

\test('fromQuantity returns OUT_OF_STOCK for negative', function (): void {
    \expect(ItemStockStatusEnum::fromQuantity(-1))->toBe(ItemStockStatusEnum::OUT_OF_STOCK);
});

\test('fromQuantity returns LOW_STOCK at threshold boundary', function (): void {
    \expect(ItemStockStatusEnum::fromQuantity(Item::LOW_STOCK_THRESHOLD))->toBe(ItemStockStatusEnum::LOW_STOCK);
});

\test('fromQuantity returns LOW_STOCK for 1', function (): void {
    \expect(ItemStockStatusEnum::fromQuantity(1))->toBe(ItemStockStatusEnum::LOW_STOCK);
});

\test('fromQuantity returns IN_STOCK above threshold', function (): void {
    \expect(ItemStockStatusEnum::fromQuantity(Item::LOW_STOCK_THRESHOLD + 1))->toBe(ItemStockStatusEnum::IN_STOCK);
});

\test('fromQuantity returns IN_STOCK for large quantity', function (): void {
    \expect(ItemStockStatusEnum::fromQuantity(1000))->toBe(ItemStockStatusEnum::IN_STOCK);
});
