<?php

declare(strict_types=1);

namespace App\Enums;

enum ChecklistEventActionEnum: string
{
    case Completed = 'completed';

    case Reopened = 'reopened';

    case Excused = 'excused';

    case ExcuseRevoked = 'excuse_revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
