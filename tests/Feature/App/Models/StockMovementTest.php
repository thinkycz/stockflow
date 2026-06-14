<?php

declare(strict_types=1);

use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovement;
use Database\Factories\StockMovementFactory;
use Database\Factories\StockMovementItemFactory;
use Database\Factories\StoreFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('incoming movement exposes getters and the correct type enum', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $movement = Typer::assertInstance(
        StockMovementFactory::new()->createOne([
            'user_id' => $user->getKey(),
            'store_id' => $warehouse->getKey(),
            'number' => 'IN-2026-0007',
            'type' => StockMovementTypeEnum::INCOMING->value,
            'note' => 'Pondělní příjem',
            'created_by' => $user->getKey(),
            'total_quantity' => 12,
            'total_value' => '123.45',
        ]),
        StockMovement::class,
    );

    \expect($movement->getNumber())->toBe('IN-2026-0007');
    \expect($movement->getType())->toBe(StockMovementTypeEnum::INCOMING);
    \expect($movement->getStoreId())->toBe($warehouse->getKey());
    \expect($movement->getSourceStoreId())->toBeNull();
    \expect($movement->getNote())->toBe('Pondělní příjem');
    \expect($movement->getCreatedBy())->toBe($user->getKey());
    \expect($movement->getTotalQuantity())->toBe(12);
    \expect($movement->getTotalValue())->toBe(123.45);
    \expect($movement->getCreatedAtDate())->toBeString();
});

\test('getDisplayLabelKey differentiates incoming / outgoing / transfer / adjustment', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = StoreFactory::new()->createOne(['user_id' => $user->getKey(), 'is_warehouse' => false]);

    $incoming = StockMovementFactory::new()->incoming()->createOne(['user_id' => $user->getKey()]);
    \expect($incoming->getDisplayLabelKey())->toBe('incoming');

    $outgoingFromWarehouse = StockMovementFactory::new()->outgoing($warehouse)->createOne(['user_id' => $user->getKey()]);
    \expect($outgoingFromWarehouse->getDisplayLabelKey())->toBe('outgoing');

    $transfer = StockMovementFactory::new()->createOne([
        'user_id' => $user->getKey(),
        'type' => StockMovementTypeEnum::OUTGOING->value,
        'source_store_id' => $retail->getKey(),
        'store_id' => $warehouse->getKey(),
    ]);
    \expect($transfer->getDisplayLabelKey())->toBe('transfer');

    $adjustment = StockMovementFactory::new()->adjustment()->createOne(['user_id' => $user->getKey()]);
    \expect($adjustment->getDisplayLabelKey())->toBe('adjustment');
});

\test('scopeSearch matches by number', function (): void {
    StockMovementFactory::new()->createOne(['number' => 'IN-2026-0001']);
    StockMovementFactory::new()->createOne(['number' => 'OUT-2026-0002']);
    StockMovementFactory::new()->createOne(['number' => 'ADJ-2026-0003']);

    \expect(StockMovement::query()->where(fn($q) => StockMovement::scopeSearch(...)($q, 'IN-'))->count())->toBe(1);
    \expect(StockMovement::query()->where(fn($q) => StockMovement::scopeSearch(...)($q, '2026'))->count())->toBe(3);
});

\test('scopeOfType filters by movement type', function (): void {
    StockMovementFactory::new()->incoming()->createOne();
    StockMovementFactory::new()->incoming()->createOne();
    StockMovementFactory::new()->adjustment()->createOne();

    \expect(StockMovement::query()->where(fn($q) => StockMovement::scopeOfType(...)($q, StockMovementTypeEnum::INCOMING))->count())->toBe(2);
    \expect(StockMovement::query()->where(fn($q) => StockMovement::scopeOfType(...)($q, StockMovementTypeEnum::ADJUSTMENT))->count())->toBe(1);
});

\test('scopeForStore and date scopes filter correctly', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $a = StockMovementFactory::new()->createOne(['store_id' => $warehouse->getKey(), 'created_at' => '2026-01-15 10:00:00']);
    StockMovementFactory::new()->createOne(['store_id' => $warehouse->getKey(), 'created_at' => '2026-03-01 10:00:00']);

    \expect(StockMovement::query()->where(fn($q) => StockMovement::scopeForStore(...)($q, $warehouse->getKey()))->count())->toBe(2);
    \expect(StockMovement::query()->where(fn($q) => StockMovement::scopeForStore(...)($q, 9999))->count())->toBe(0);

    \expect(StockMovement::query()->where(fn($q) => StockMovement::scopeFromDate(...)($q, '2026-02-01'))->count())->toBe(1);
    \expect(StockMovement::query()->where(fn($q) => StockMovement::scopeUntilDate(...)($q, '2026-02-01'))->count())->toBe(1);
});

\test('relationships point at the expected related models', function (): void {
    $movement = Typer::assertInstance(
        StockMovementFactory::new()->createOne(),
        StockMovement::class,
    );

    \expect($movement->store())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    \expect($movement->sourceStore())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    \expect($movement->creator())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    \expect($movement->movementItems())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    \expect($movement->items())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

\test('getItemsCount uses the aggregate when loaded, otherwise falls back to the relation', function (): void {
    $movement = Typer::assertInstance(
        StockMovementFactory::new()->createOne(),
        StockMovement::class,
    );
    StockMovementItemFactory::new()->count(3)->create(['stock_movement_id' => $movement->getKey()]);

    \expect($movement->getItemsCount())->toBe(3);

    $movement->setAttribute('movement_items_count', 7);
    \expect($movement->getItemsCount())->toBe(7);
});
