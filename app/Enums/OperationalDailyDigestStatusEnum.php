<?php

declare(strict_types=1);

namespace App\Enums;

enum OperationalDailyDigestStatusEnum: string
{
    case PENDING = 'pending';

    case QUEUED = 'queued';

    case SENT = 'sent';

    case FAILED = 'failed';

    /**
     * Return all status values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_column(self::cases(), 'value');
    }
}
