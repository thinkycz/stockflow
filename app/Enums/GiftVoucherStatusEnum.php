<?php

declare(strict_types=1);

namespace App\Enums;

enum GiftVoucherStatusEnum: string
{
    case Active = 'active';

    case Expired = 'expired';

    case Redeemed = 'redeemed';

    case Voided = 'voided';

    /**
     * Get possible values, including the derived expired status.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }

    /**
     * Persisted status values (expiration is derived).
     *
     * @return list<string>
     */
    public static function storedValues(): array
    {
        return [self::Active->value, self::Redeemed->value, self::Voided->value];
    }
}
