<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\OperationalActivity\OperationalDigestRetentionService;
use App\Models\OperationalActivity;
use App\Models\OperationalDailyDigest;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class PruneOperationalDigestHistoryJob implements ShouldQueue
{
    use Queueable;

    /**
     * Delete expired operational journal and digest records.
     */
    public function handle(): void
    {
        $cutoff = (new OperationalDigestRetentionService())->cutoff(CarbonImmutable::now());

        OperationalActivity::query()->where('occurred_at', '<', $cutoff->utc())->delete();
        OperationalDailyDigest::query()->whereDate('digest_date', '<', $cutoff->toDateString())->delete();
    }
}
