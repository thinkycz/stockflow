<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OperationalActivity;
use App\Models\OperationalDailyDigest;
use App\Services\DailyOperationalDigestBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class PruneOperationalDigestHistoryJob implements ShouldQueue
{
    use Queueable;

    private const RETENTION_DAYS = 90;

    /**
     * Delete expired operational journal and digest records.
     */
    public function handle(): void
    {
        $cutoff = CarbonImmutable::now(DailyOperationalDigestBuilder::BUSINESS_TIMEZONE)
            ->startOfDay()
            ->subDays(self::RETENTION_DAYS);

        OperationalActivity::query()->where('occurred_at', '<', $cutoff->utc())->delete();
        OperationalDailyDigest::query()->whereDate('digest_date', '<', $cutoff->toDateString())->delete();
    }
}
