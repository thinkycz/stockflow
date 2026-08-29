<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AssistantActionAudit;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class PruneAssistantActionAuditsJob implements ShouldQueue
{
    use Queueable;

    private const BUSINESS_TIMEZONE = 'Europe/Prague';

    private const RETENTION_DAYS = 90;

    /**
     * Delete assistant action audit rows outside the retention window.
     */
    public function handle(): void
    {
        $cutoff = CarbonImmutable::now(self::BUSINESS_TIMEZONE)
            ->startOfDay()
            ->subDays(self::RETENTION_DAYS);

        AssistantActionAudit::query()->where('proposed_at', '<', $cutoff->utc())->delete();
    }
}
