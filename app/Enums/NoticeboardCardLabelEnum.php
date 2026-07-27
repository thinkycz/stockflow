<?php

declare(strict_types=1);

namespace App\Enums;

enum NoticeboardCardLabelEnum: string
{
    case Information = 'information';

    case Important = 'important';

    case Task = 'task';

    case Event = 'event';

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
