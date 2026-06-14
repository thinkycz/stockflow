<?php

declare(strict_types=1);

use App\Enums\AdjustmentReasonEnum;
use App\Models\StockMovementItem;
use Database\Factories\StockMovementItemFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('getters round-trip the persisted attributes', function (): void {
    $row = Typer::assertInstance(
        StockMovementItemFactory::new()->createOne([
            'quantity' => 10,
            'total' => '99.99',
            'quantity_before' => 5,
            'quantity_after' => 15,
            'quantity_difference' => 10,
            'adjustment_reason' => AdjustmentReasonEnum::DAMAGED->value,
        ]),
        StockMovementItem::class,
    );

    \expect($row->getStockMovementId())->toBeInt();
    \expect($row->getItemId())->toBeInt();
    \expect($row->getQuantity())->toBe(10);
    \expect($row->getTotal())->toBe(99.99);
    \expect($row->getQuantityBefore())->toBe(5);
    \expect($row->getQuantityAfter())->toBe(15);
    \expect($row->getQuantityDifference())->toBe(10);
    \expect($row->getAdjustmentReason())->toBe(AdjustmentReasonEnum::DAMAGED);
});

\test('non-adjustment row exposes null adjustment fields', function (): void {
    $row = Typer::assertInstance(
        StockMovementItemFactory::new()->createOne([
            'quantity' => 7,
            'quantity_before' => null,
            'quantity_after' => null,
            'quantity_difference' => null,
            'adjustment_reason' => null,
        ]),
        StockMovementItem::class,
    );

    \expect($row->getQuantityBefore())->toBeNull();
    \expect($row->getQuantityAfter())->toBeNull();
    \expect($row->getQuantityDifference())->toBeNull();
    \expect($row->getAdjustmentReason())->toBeNull();
});

\test('scopeSearch is a documented no-op', function (): void {
    StockMovementItemFactory::new()->count(2)->create();

    \expect(StockMovementItem::query()->count())->toBe(2);
    \expect(StockMovementItem::query()->where(fn($q) => StockMovementItem::scopeSearch(...)($q, 'nope'))->count())->toBe(2);
});

\test('adjustment factory state populates before/after/difference', function (): void {
    $row = Typer::assertInstance(
        StockMovementItemFactory::new()->adjustment(AdjustmentReasonEnum::INVENTORY_CORRECTION)->createOne(),
        StockMovementItem::class,
    );

    \expect($row->getQuantity())->toBeNull();
    \expect($row->getQuantityBefore())->toBeInt();
    \expect($row->getQuantityAfter())->toBeInt();
    \expect($row->getAdjustmentReason())->toBe(AdjustmentReasonEnum::INVENTORY_CORRECTION);
    \expect($row->getQuantityDifference())->toBe(
        $row->getQuantityAfter() - $row->getQuantityBefore(),
    );
});

\test('relationships point at the expected related models', function (): void {
    $row = Typer::assertInstance(StockMovementItemFactory::new()->createOne(), StockMovementItem::class);

    \expect($row->stockMovement())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    \expect($row->item())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

\test('eager-loaded relations come back through the get* helpers', function (): void {
    $row = Typer::assertInstance(StockMovementItemFactory::new()->createOne(), StockMovementItem::class);
    $movement = $row->stockMovement;
    $item = $row->item;
    $row->setRelation('stockMovement', $movement);
    $row->setRelation('item', $item);

    \expect($row->getStockMovement())->toBe($movement);
    \expect($row->getItem())->toBe($item);
});
