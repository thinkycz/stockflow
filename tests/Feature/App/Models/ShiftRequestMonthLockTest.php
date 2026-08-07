<?php

declare(strict_types=1);

use App\Models\ShiftRequestMonthLock;

\test('month lock exposes its period and actor', function (): void {
    $lock = ShiftRequestMonthLock::factory()->createOne(['year' => 2027, 'month' => 2]);

    \expect($lock->getYear())->toBe(2027)
        ->and($lock->getMonth())->toBe(2)
        ->and($lock->getLockedByUserId())->not->toBeNull()
        ->and($lock->getLockedAt())->not->toBeNull();
});
