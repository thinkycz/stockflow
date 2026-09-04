<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\BankStatements\BankStatementService;
use Illuminate\Console\Command;

final class RequeueBankStatementImportsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stockflow:bank-imports:requeue-active';

    /**
     * @var string
     */
    protected $description = 'Fence and requeue active bank imports during maintenance with workers stopped.';

    /**
     * Queue replacements while preserving review data and report dispatch failures.
     */
    public function handle(): int
    {
        $result = (new BankStatementService())->requeueActiveImports();
        $this->info('Queued: ' . $result['queued'] . '; failed: ' . $result['failed']);

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
