<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceDeviationReviewDecisionEnum: string
{
    case APPROVED = 'approved';

    case REJECTED = 'rejected';

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
