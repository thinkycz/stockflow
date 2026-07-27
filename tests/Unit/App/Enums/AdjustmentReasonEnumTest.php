<?php

declare(strict_types=1);

use App\Enums\AdjustmentReasonEnum;

\test('values returns all case values', function (): void {
    \expect(AdjustmentReasonEnum::values())->toBe([
        'initial_stock',
        'missing',
        'stolen',
        'damaged',
        'inventory_correction',
        'other',
    ]);
});

\test('cases returns six entries', function (): void {
    \expect(AdjustmentReasonEnum::cases())->toHaveCount(6);
});

\test('from creates enum from valid strings', function (): void {
    \expect(AdjustmentReasonEnum::from('initial_stock'))->toBe(AdjustmentReasonEnum::INITIAL_STOCK);
    \expect(AdjustmentReasonEnum::from('missing'))->toBe(AdjustmentReasonEnum::MISSING);
    \expect(AdjustmentReasonEnum::from('stolen'))->toBe(AdjustmentReasonEnum::STOLEN);
    \expect(AdjustmentReasonEnum::from('damaged'))->toBe(AdjustmentReasonEnum::DAMAGED);
    \expect(AdjustmentReasonEnum::from('inventory_correction'))->toBe(AdjustmentReasonEnum::INVENTORY_CORRECTION);
    \expect(AdjustmentReasonEnum::from('other'))->toBe(AdjustmentReasonEnum::OTHER);
});

\test('tryFrom returns null for invalid string', function (): void {
    \expect(AdjustmentReasonEnum::tryFrom('nonexistent'))->toBeNull();
});
