<?php

declare(strict_types=1);

namespace App\Enums;

enum RemovalOutcomeEnum: string
{
    case DELETED = 'deleted';

    case ARCHIVED = 'archived';

    case BLOCKED = 'blocked';

    /**
     * Get possible values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn(self $outcome): string => $outcome->value, self::cases());
    }
}
