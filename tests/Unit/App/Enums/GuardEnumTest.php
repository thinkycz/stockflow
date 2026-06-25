<?php

declare(strict_types=1);

use App\Enums\GuardEnum;

\test('values returns all case values', function (): void {
    \expect(GuardEnum::values())->toBe(['users']);
});

\test('cases returns one entry', function (): void {
    \expect(GuardEnum::cases())->toHaveCount(1);
});

\test('from creates enum from valid string', function (): void {
    \expect(GuardEnum::from('users'))->toBe(GuardEnum::USERS);
});

\test('tryFrom returns null for invalid string', function (): void {
    \expect(GuardEnum::tryFrom('admins'))->toBeNull();
});
