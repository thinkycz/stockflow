<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\OperationalActivity\DailyOperationalDigestBuilder;
use App\Domain\OperationalActivity\DailyOperationalDigestService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Thinkycz\LaravelCore\Support\Typer;

class CreateDailyOperationalDigestJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create at most one missing daily digest for the deployment company.
     */
    public function handle(DailyOperationalDigestService $service): void
    {
        $company = Typer::assertInstance(User::query()
            ->where('is_admin', true)
            ->whereNull('parent_user_id')
            ->sole(), User::class);

        $service->createNext($company, CarbonImmutable::now(DailyOperationalDigestBuilder::BUSINESS_TIMEZONE));
    }
}
