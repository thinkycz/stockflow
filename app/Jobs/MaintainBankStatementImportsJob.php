<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\BankStatements\BankStatementService;
use App\Enums\BankStatementStatusEnum;
use App\Models\BankStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Thinkycz\LaravelCore\Support\Config;
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
        $queued = BankStatement::query()
            ->where('status', BankStatementStatusEnum::QUEUED->value)
            ->where('queued_at', '<=', \now()->subMinutes(5))
            ->select(['id', 'parse_generation'])
            ->lazyById(100);

        foreach ($queued as $statement) {
            $id = $statement->getKey();
            $generation = $statement->getParseGeneration();
            $claimed = BankStatement::query()
                ->whereKey($id)
                ->where('parse_generation', $generation)
                ->where('status', BankStatementStatusEnum::QUEUED->value)
                ->where('queued_at', '<=', \now()->subMinutes(5))
                ->update(['queued_at' => \now(), 'updated_at' => \now()]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                \dispatch(new ParseBankStatementJob($id, $generation));
            } catch (Throwable) {
                $statement = BankStatement::query()->find($id);
                if ($statement instanceof BankStatement) {
                    (new BankStatementService())->fail($statement, 'queue_dispatch_failed', $generation);
                }
            }
        }

        $cutoff = \now()->subSeconds(Config::inject()->assertInt('queue.connections.assistant.retry_after') + 60)->toDateTimeString();
        $processing = BankStatement::query()
            ->where('status', BankStatementStatusEnum::PROCESSING->value)
            ->where('started_at', '<=', $cutoff)
            ->select(['id', 'parse_generation'])
            ->lazyById(100);

        foreach ($processing as $statement) {
            (new BankStatementService())->fail($statement, 'processing_timeout', $statement->getParseGeneration(), $cutoff);
        }
    }
}
