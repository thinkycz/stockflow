<?php

declare(strict_types=1);

use App\Enums\BankStatementStatusEnum;
use App\Jobs\MaintainBankStatementImportsJob;
use App\Jobs\ParseBankStatementJob;
use App\Models\BankStatement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('bank import maintenance redispatches stale queued rows and fails stale processing rows', function (): void {
    Carbon::setTestNow('2026-09-02 12:00:00');
    Queue::fake();
    [, $store] = \createIsolatedUserWithWarehouse();
    $queued = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::QUEUED->value,
        'queued_at' => \now()->subMinutes(6),
        'updated_at' => \now()->subMinutes(6),
    ]);
    $processing = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::PROCESSING->value,
        'started_at' => \now()->subMinutes(5),
        'updated_at' => \now()->subMinutes(5),
    ]);

    $job = new MaintainBankStatementImportsJob();
    $job->handle();
    $job->handle();

    \expect($queued->fresh()?->getStatus())->toBe(BankStatementStatusEnum::QUEUED)
        ->and($queued->fresh()?->getQueuedAt()?->equalTo(\now()))->toBeTrue()
        ->and($processing->fresh()?->getStatus())->toBe(BankStatementStatusEnum::FAILED)
        ->and($processing->fresh()?->getLastError())->toBe('processing_timeout');
    Queue::assertPushed(ParseBankStatementJob::class, 1);
});

\test('bank import maintenance makes a failed redispatch safely retryable', function (): void {
    Carbon::setTestNow('2026-09-02 12:00:00');
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('redis unavailable'));
    [, $store] = \createIsolatedUserWithWarehouse();
    $statement = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::QUEUED->value,
        'queued_at' => \now()->subMinutes(6),
        'updated_at' => \now()->subMinutes(6),
    ]);

    (new MaintainBankStatementImportsJob())->handle();

    \expect($statement->fresh()?->getStatus())->toBe(BankStatementStatusEnum::FAILED)
        ->and($statement->fresh()?->getLastError())->toBe('queue_dispatch_failed')
        ->and(BankStatement::query()->count())->toBe(1);
});
