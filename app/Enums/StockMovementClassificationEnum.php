<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementClassificationEnum: string
{
    case CONSUMPTION = 'consumption';

    case DAMAGED = 'damaged';

    case STOLEN = 'stolen';

    case MISSING = 'missing';

    case INVENTORY_CORRECTION = 'inventory_correction';

    case INITIAL_STOCK = 'initial_stock';

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

    /**
     * Whether the classification is valid for a negative stock change.
     */
    public function supportsNegativeDifference(): bool
    {
        return $this !== self::INVENTORY_CORRECTION;
    }

    /**
     * Whether the classification is valid for a positive stock change.
     */
    public function supportsPositiveDifference(): bool
    {
        return $this === self::INVENTORY_CORRECTION || $this === self::INITIAL_STOCK || $this === self::OTHER;
    }
}
