<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementOriginEnum: string
{
    case MANUAL = 'manual';

    case INVENTORY = 'inventory';

    case MIGRATION = 'migration';

    case REVERSAL = 'reversal';

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
