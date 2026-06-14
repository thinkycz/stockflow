<?php

declare(strict_types=1);

use App\Enums\StockMovementTypeEnum;
use App\Services\StockMovementTypeResolver;

\test('adjustment mode wins over store pair', function (): void {
    \expect((new StockMovementTypeResolver())->resolve(true, 1, 2))->toBe(StockMovementTypeEnum::ADJUSTMENT);
    \expect((new StockMovementTypeResolver())->resolve(true, null, 5))->toBe(StockMovementTypeEnum::ADJUSTMENT);
});

\test('incoming is resolved when only the destination is provided', function (): void {
    \expect((new StockMovementTypeResolver())->resolve(false, null, 7))->toBe(StockMovementTypeEnum::INCOMING);
});

\test('outgoing is resolved when both source and destination are provided', function (): void {
    \expect((new StockMovementTypeResolver())->resolve(false, 3, 4))->toBe(StockMovementTypeEnum::OUTGOING);
});

\test('same source and destination fails with a validation error', function (): void {
    (new StockMovementTypeResolver())->resolve(false, 5, 5);
})->throws(Illuminate\Validation\ValidationException::class);

\test('missing destination fails with a validation error', function (): void {
    (new StockMovementTypeResolver())->resolve(false, null, null);
})->throws(Illuminate\Validation\ValidationException::class);
