<?php

declare(strict_types=1);

namespace App\Enums;

enum StoreStatusEnum: string
{
    case ACTIVE = 'active';

    case INACTIVE = 'inactive';

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
