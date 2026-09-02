<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class Money
{
    /**
     * Preserve a validated request or JSON decimal as text at the service boundary.
     */
    public static function input(mixed $value): string
    {
        if (\is_int($value) || \is_float($value) || \is_string($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Money input must be numeric text.');
    }

    /**
     * Normalize a business input or stored decimal to CZK cents.
     */
    public static function of(float|int|string $value): BigDecimal
    {
        return BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Create an exact zero amount.
     */
    public static function zero(): BigDecimal
    {
        return BigDecimal::zero()->toScale(2);
    }

    /**
     * Convert only a completed presentation amount to the legacy float payload.
     */
    public static function present(BigDecimal $value): float
    {
        return $value->toFloat();
    }
}
