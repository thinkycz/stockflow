<?php

declare(strict_types=1);

namespace App\Enums;

enum GiftVoucherEventTypeEnum: string
{
    case Issued = 'issued';

    case Redeemed = 'redeemed';

    case Voided = 'voided';

    case RedemptionReversed = 'redemption_reversed';

    /**
     * Get possible values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
