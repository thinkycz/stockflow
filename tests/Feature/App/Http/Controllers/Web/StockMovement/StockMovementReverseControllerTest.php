<?php

declare(strict_types=1);

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\StockMovementService;

\test('reversing an incoming movement preserves both ledger entries and restores stock', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => 12.3456]);
    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming', 'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 5]],
    ], $user);

    $this->be($user, 'users')->post("/stock-movements/{$movement->getKey()}/reverse", [
        'reason' => 'Duplicate receipt',
    ])->assertRedirect('/stock-movements');

    $movement->refresh();
    $reversal = StockMovement::query()->where('reversal_of_id', $movement->getKey())->firstOrFail();
    \expect($movement->getReversedAt())->not->toBeNull()
        ->and($reversal->getType())->toBe(StockMovementTypeEnum::REVERSAL)
        ->and($reversal->getReversalReason())->toBe('Duplicate receipt')
        ->and($reversal->getMovementItems()->firstOrFail()->getUnitCost())->toBe(12.35)
        ->and((int) StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(0);
});

\test('reversing a transfer restores both stores', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create(['store_id' => $warehouse->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);
    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'transfer', 'source_store_id' => $warehouse->getKey(), 'store_id' => $retail->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 4]],
    ], $user);

    $this->be($user, 'users')->post("/stock-movements/{$movement->getKey()}/reverse", ['reason' => 'Wrong store'])->assertRedirect();

    \expect((int) StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(10)
        ->and((int) StoreItem::query()->where('store_id', $retail->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(0);
});

\test('reversing an adjustment applies its inverse to the current quantity', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create(['store_id' => $warehouse->getKey(), 'item_id' => $item->getKey(), 'quantity' => 8]);
    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'adjustment', 'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity_after' => 3, 'adjustment_reason' => AdjustmentReasonEnum::DAMAGED->value]],
    ], $user);
    StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->update(['quantity' => 5]);

    $this->be($user, 'users')->post("/stock-movements/{$movement->getKey()}/reverse", ['reason' => 'Correction'])->assertRedirect();

    \expect((int) StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(10);
});

\test('movement can only be reversed once and reversal cannot make stock negative', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming', 'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 5]],
    ], $user);
    StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->update(['quantity' => 2]);

    $this->be($user, 'users')->post("/stock-movements/{$movement->getKey()}/reverse", ['reason' => 'Invalid'])->assertStatus(422);
    \expect(StockMovement::query()->where('reversal_of_id', $movement->getKey())->exists())->toBeFalse();

    StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->update(['quantity' => 5]);
    $this->post("/stock-movements/{$movement->getKey()}/reverse", ['reason' => 'Valid'])->assertRedirect();
    $this->post("/stock-movements/{$movement->getKey()}/reverse", ['reason' => 'Again'])->assertStatus(422);
    \expect(StockMovement::query()->where('reversal_of_id', $movement->getKey())->count())->toBe(1);
});
