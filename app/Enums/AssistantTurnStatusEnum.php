<?php

declare(strict_types=1);

namespace App\Enums;

enum AssistantTurnStatusEnum: string
{
    case QUEUED = 'queued';

    case RUNNING = 'running';

    case AWAITING_APPROVAL = 'awaiting_approval';

    case COMPLETED = 'completed';

    case CANCEL_REQUESTED = 'cancel_requested';

    case CANCELLED = 'cancelled';

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

    /**
     * Determine whether the turn no longer requires queue processing.
     */
    public function terminal(): bool
    {
        return \in_array($this, [self::AWAITING_APPROVAL, self::COMPLETED, self::CANCELLED, self::FAILED], true);
    }
}
