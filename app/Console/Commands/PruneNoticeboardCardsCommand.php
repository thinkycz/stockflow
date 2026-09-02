<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NoticeboardCard;
use App\Services\NoticeboardCardService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneNoticeboardCardsCommand extends Command
{
    /**
     * Console command signature.
     *
     * @var string
     */
    protected $signature = 'stockflow:prune-noticeboard-cards';

    /**
     * Console command description.
     *
     * @var string
     */
    protected $description = 'Permanently remove noticeboard cards trashed for more than thirty days.';

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        $deleted = 0;
        $failed = 0;
        $service = new NoticeboardCardService();

        NoticeboardCard::query()
            ->onlyTrashed()
            ->whereHas('store', static fn($query) => $query->where('status', 'active'))
            ->where('deleted_at', '<=', Carbon::now()->subDays(30))
            ->orderBy('id')
            ->chunkById(100, static function ($cards) use ($service, &$deleted, &$failed): void {
                foreach ($cards as $card) {
                    if ($service->forceDelete($card)) {
                        ++$deleted;
                    } else {
                        ++$failed;
                    }
                }
            });

        $this->info("Pruned {$deleted} noticeboard cards.");

        if ($failed > 0) {
            $this->error("Failed to prune {$failed} noticeboard cards.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
