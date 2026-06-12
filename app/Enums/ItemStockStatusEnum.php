<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Item;

enum ItemStockStatusEnum: string
{
    case IN_STOCK = 'in_stock';

    case LOW_STOCK = 'low_stock';

    case OUT_OF_STOCK = 'out_of_stock';

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
     * Compute status from a quantity value.
     */
    public static function fromQuantity(int $quantity): self
    {
        if ($quantity <= 0) {
            return self::OUT_OF_STOCK;
        }

        if ($quantity <= Item::LOW_STOCK_THRESHOLD) {
            return self::LOW_STOCK;
        }

        return self::IN_STOCK;
    }
}
