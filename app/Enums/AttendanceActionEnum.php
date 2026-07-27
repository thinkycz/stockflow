<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceActionEnum: string
{
    case ARRIVAL = 'arrival';

    case BREAK_START = 'break_start';

    case BREAK_END = 'break_end';

    case DEPARTURE = 'departure';

    /**
     * Return all persisted action values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn(self $action): string => $action->value, self::cases());
    }
}
