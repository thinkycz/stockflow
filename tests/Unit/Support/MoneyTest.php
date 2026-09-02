<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use Brick\Math\RoundingMode;

\test('money keeps cent arithmetic exact at upper payroll input bounds', function (): void {
    $amount = Money::of('999999.99')
        ->multipliedBy(Money::of('999999999999.99'))
        ->toScale(2, RoundingMode::HalfUp);

    \expect((string) $amount)->toBe('999999989999990000.00')
        ->and((string) Money::of('0.01')->plus(Money::of('0.06'))->minus(Money::of('0.07')))->toBe('0.00')
        ->and((string) Money::of('0.01')->plus(Money::of('0.05'))->minus(Money::of('0.07')))->toBe('-0.01');
});

\test('money input preserves validated decimal text until normalization', function (): void {
    \expect(Money::input('0001.20'))->toBe('0001.20')
        ->and((string) Money::of(Money::input('0001.20')))->toBe('1.20');
});
