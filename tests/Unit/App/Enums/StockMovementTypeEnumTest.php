<?php

declare(strict_types=1);

use App\Enums\StockMovementTypeEnum;

\test('values returns all case values', function (): void {
    \expect(StockMovementTypeEnum::values())->toBe(['incoming', 'outgoing', 'adjustment']);
});

\test('prefix returns IN for incoming', function (): void {
    \expect(StockMovementTypeEnum::INCOMING->prefix())->toBe('IN');
});

\test('prefix returns OUT for outgoing', function (): void {
    \expect(StockMovementTypeEnum::OUTGOING->prefix())->toBe('OUT');
});

\test('prefix returns ADJ for adjustment', function (): void {
    \expect(StockMovementTypeEnum::ADJUSTMENT->prefix())->toBe('ADJ');
});

\test('from creates enum from valid string', function (): void {
    \expect(StockMovementTypeEnum::from('incoming'))->toBe(StockMovementTypeEnum::INCOMING);
    \expect(StockMovementTypeEnum::from('outgoing'))->toBe(StockMovementTypeEnum::OUTGOING);
    \expect(StockMovementTypeEnum::from('adjustment'))->toBe(StockMovementTypeEnum::ADJUSTMENT);
});

\test('tryFrom returns null for invalid string', function (): void {
    \expect(StockMovementTypeEnum::tryFrom('invalid'))->toBeNull();
});
