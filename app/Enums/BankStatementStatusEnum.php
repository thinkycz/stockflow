<?php

declare(strict_types=1);

namespace App\Enums;

enum BankStatementStatusEnum: string
{
    case QUEUED = 'queued';

    case PROCESSING = 'processing';

    case REVIEW = 'review';

    case CONFIRMED = 'confirmed';

    case FAILED = 'failed';

    /**
     * Get possible values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn(self $status): string => $status->value, self::cases());
    }
}
