<?php

declare(strict_types=1);

namespace App\Enums;

enum PayrollAdjustmentTypeEnum: string
{
    case TIP = 'tip';

    case DEDUCTION = 'deduction';

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
