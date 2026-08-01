<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\ChecklistService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateDailyChecklistsCommand extends Command
{
    /**
     * Console command signature. @var string.
     */
    protected $signature = 'stockflow:generate-daily-checklists {--date=}';

    /**
     * Console command description. @var string.
     */
    protected $description = 'Create idempotent daily checklist snapshots for active retail stores.';

    /**
     * Execute daily idempotent checklist generation.
     */
    public function handle(): int
    {
        $dateOption = $this->option('date');
        $date = \is_string($dateOption) && $dateOption !== ''
            ? CarbonImmutable::createFromFormat('!Y-m-d', $dateOption, ChecklistService::TIMEZONE)
            : CarbonImmutable::now(ChecklistService::TIMEZONE);
        if (!$date instanceof CarbonImmutable) {
            $this->error('Invalid date. Use Y-m-d.');

            return self::FAILURE;
        }

        $service = new ChecklistService();
        $count = 0;
        $query = Store::query();
        Store::scopeActive($query);
        Store::scopeRetail($query);
        $query->orderBy('id')->chunkById(100, static function ($stores) use ($service, $date, &$count): void {
            foreach ($stores as $store) {
                $service->ensureDay($store, $date);
                ++$count;
            }
        });

        $this->info("Generated {$count} daily checklists for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
