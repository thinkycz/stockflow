<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementTypeEnum: string
{
    case INCOMING = 'incoming';

    case TRANSFER = 'transfer';

    case CONSUMPTION = 'consumption';

    case ADJUSTMENT = 'adjustment';

    case INVENTORY_RECONCILIATION = 'inventory_reconciliation';

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
     * Number prefix used for the auto-generated number.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::INCOMING => 'IN',
            self::TRANSFER => 'TR',
            self::CONSUMPTION => 'CON',
            self::ADJUSTMENT => 'ADJ',
            self::INVENTORY_RECONCILIATION => 'REC',
        };
    }
}
