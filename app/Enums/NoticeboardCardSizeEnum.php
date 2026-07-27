<?php

declare(strict_types=1);

namespace App\Enums;

enum NoticeboardCardSizeEnum: string
{
    case Small = 'small';

    case Medium = 'medium';

    case Large = 'large';

    /**
     * Get possible values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
