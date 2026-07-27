<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;

\test('values returns all case values', function (): void {
    \expect(StoreStatusEnum::values())->toBe(['active', 'inactive']);
});

\test('cases returns two entries', function (): void {
    \expect(StoreStatusEnum::cases())->toHaveCount(2);
});

\test('from creates enum from valid strings', function (): void {
    \expect(StoreStatusEnum::from('active'))->toBe(StoreStatusEnum::ACTIVE);
    \expect(StoreStatusEnum::from('inactive'))->toBe(StoreStatusEnum::INACTIVE);
});

\test('tryFrom returns null for invalid string', function (): void {
    \expect(StoreStatusEnum::tryFrom('unknown'))->toBeNull();
});
