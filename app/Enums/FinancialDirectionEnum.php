<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialDirectionEnum: string
{
    case INCOME = 'income';

    case EXPENSE = 'expense';

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
