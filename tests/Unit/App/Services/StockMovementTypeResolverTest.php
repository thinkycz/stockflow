<?php

declare(strict_types=1);

use App\Enums\StockMovementTypeEnum;
use App\Services\StockMovementTypeResolver;

\test('adjustment mode wins over store pair', function (): void {
    \expect((new StockMovementTypeResolver())->resolve('adjustment', 1, 2))->toBe(StockMovementTypeEnum::ADJUSTMENT);
    \expect((new StockMovementTypeResolver())->resolve('adjustment', null, 5))->toBe(StockMovementTypeEnum::ADJUSTMENT);
});

\test('incoming is resolved when only the destination is provided', function (): void {
    \expect((new StockMovementTypeResolver())->resolve('transfer', null, 7))->toBe(StockMovementTypeEnum::INCOMING);
});

\test('transfer is resolved when both source and destination are provided', function (): void {
    \expect((new StockMovementTypeResolver())->resolve('transfer', 3, 4))->toBe(StockMovementTypeEnum::TRANSFER);
});

\test('consumption is resolved for one store without a source', function (): void {
    \expect((new StockMovementTypeResolver())->resolve('consumption', null, 4))->toBe(StockMovementTypeEnum::CONSUMPTION);
});

\test('same source and destination fails with a validation error', function (): void {
    (new StockMovementTypeResolver())->resolve('transfer', 5, 5);
})->throws(Illuminate\Validation\ValidationException::class);

\test('missing destination fails with a validation error', function (): void {
    (new StockMovementTypeResolver())->resolve('transfer', null, null);
})->throws(Illuminate\Validation\ValidationException::class);
