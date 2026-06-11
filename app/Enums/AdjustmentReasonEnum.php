<?php

declare(strict_types=1);

namespace App\Enums;

enum AdjustmentReasonEnum: string
{
    case INITIAL_STOCK = 'initial_stock';

    case MISSING = 'missing';

    case STOLEN = 'stolen';

    case DAMAGED = 'damaged';

    case INVENTORY_CORRECTION = 'inventory_correction';

    case OTHER = 'other';

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
