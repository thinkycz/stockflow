<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BankStatementStatusEnum;
use App\Models\BankStatement;
use App\Services\BankStatementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class MaintainBankStatementImportsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Allow only one maintenance attempt so overlapping recovery cannot amplify dispatches.
     */
    public int $tries = 1;

    /**
     * Recover undispatched imports and terminate abandoned parser attempts.
     */
    public function handle(): void
    {
        $queuedIds = BankStatement::query()
            ->where('status', BankStatementStatusEnum::QUEUED->value)
            ->where('queued_at', '<=', \now()->subMinutes(5))
            ->pluck('id');

        foreach ($queuedIds as $value) {
            $id = Typer::assertInt($value);
            $claimed = BankStatement::query()
                ->whereKey($id)
                ->where('status', BankStatementStatusEnum::QUEUED->value)
                ->where('queued_at', '<=', \now()->subMinutes(5))
                ->update(['queued_at' => \now(), 'updated_at' => \now()]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                \dispatch(new ParseBankStatementJob($id));
            } catch (Throwable) {
                $statement = BankStatement::query()->find($id);
                if ($statement instanceof BankStatement) {
                    (new BankStatementService())->fail($statement, 'queue_dispatch_failed');
                }
            }
        }

        $processingIds = BankStatement::query()
            ->where('status', BankStatementStatusEnum::PROCESSING->value)
            ->where('started_at', '<=', \now()->subSeconds(
                Config::inject()->assertInt('queue.connections.assistant.retry_after') + 60,
            ))
            ->pluck('id');

        foreach ($processingIds as $value) {
            $statement = BankStatement::query()->find(Typer::assertInt($value));
            if ($statement instanceof BankStatement) {
                (new BankStatementService())->fail($statement, 'processing_timeout');
            }
        }
    }
}
