<?php

declare(strict_types=1);

namespace App\Enums;

enum AssistantActionStatusEnum: string
{
    case PENDING_APPROVAL = 'pending_approval';

    case APPROVED = 'approved';

    case EDITED = 'edited';

    case REJECTED = 'rejected';

    case RUNNING = 'running';

    case SUCCEEDED = 'succeeded';

    case FAILED = 'failed';

    case UNCERTAIN = 'uncertain';

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
