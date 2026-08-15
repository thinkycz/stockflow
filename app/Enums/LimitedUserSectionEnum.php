<?php

declare(strict_types=1);

namespace App\Enums;

enum LimitedUserSectionEnum: string
{
    case INCOMING = 'incoming';

    case CONSUMPTION = 'consumption';

    case STATEMENTS = 'statements';

    case INVENTORY_COUNTS = 'inventory_counts';

    case SHIFTS = 'shifts';

    case ATTENDANCE = 'attendance';

    case CHECKLISTS = 'checklists';

    case RECIPES = 'recipes';

    case GIFT_VOUCHERS = 'gift_vouchers';

    /**
     * Get possible values in their presentation order.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
