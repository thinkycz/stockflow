<?php

declare(strict_types=1);

use App\Enums\FilesystemDiskEnum;

\test('values returns all case values', function (): void {
    \expect(FilesystemDiskEnum::values())->toBe(['local', 'public', 'private']);
});

\test('cases returns all enum instances', function (): void {
    $cases = FilesystemDiskEnum::cases();

    \expect($cases)->toHaveCount(3);
    \expect($cases[0])->toBe(FilesystemDiskEnum::Local);
    \expect($cases[1])->toBe(FilesystemDiskEnum::Public);
    \expect($cases[2])->toBe(FilesystemDiskEnum::Private);
});

\test('from creates enum from valid string', function (): void {
    \expect(FilesystemDiskEnum::from('local'))->toBe(FilesystemDiskEnum::Local);
    \expect(FilesystemDiskEnum::from('public'))->toBe(FilesystemDiskEnum::Public);
    \expect(FilesystemDiskEnum::from('private'))->toBe(FilesystemDiskEnum::Private);
});

\test('tryFrom returns null for invalid string', function (): void {
    \expect(FilesystemDiskEnum::tryFrom('invalid'))->toBeNull();
});
