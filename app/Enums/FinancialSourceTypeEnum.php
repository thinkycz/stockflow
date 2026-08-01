<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialSourceTypeEnum: string
{
    case REVENUE = 'revenue';

    case STOCK_MOVEMENT = 'stock_movement';

    case WAGE = 'wage';

    /**
     * Get possible values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
