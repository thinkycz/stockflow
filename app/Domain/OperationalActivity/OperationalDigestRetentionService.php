<?php

declare(strict_types=1);

namespace App\Domain\OperationalActivity;

use Carbon\CarbonImmutable;

class OperationalDigestRetentionService
{
    /**
     * Earliest retained business day, shared by generation and pruning.
     */
    public function cutoff(CarbonImmutable $now): CarbonImmutable
    {
        return $now->setTimezone(DailyOperationalDigestBuilder::BUSINESS_TIMEZONE)->startOfDay()->subDays(90);
    }
}
