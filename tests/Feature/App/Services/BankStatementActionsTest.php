<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementReconciliationService;
use App\Domain\BankStatements\BankStatementService;
use App\Enums\BankStatementStatusEnum;
use App\Enums\FilesystemDiskEnum;
use App\Jobs\ParseBankStatementJob;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Thinkycz\LaravelCore\Support\Resolver;

\test('deletion removes rows and original and fences every late parser callback', function (string $status): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $bank = BankStatement::factory()->forStore($store)->create(['status' => $status]);
    $transaction = BankStatementTransaction::factory()->forStatement($bank)->create();
    Storage::disk(FilesystemDiskEnum::Private->value)->put($bank->getOriginalPath(), 'encrypted source');
    $service = new BankStatementService();
    $service->delete($bank, $user);
    $service->cleanupDeletedOriginals();
    $service->applyParsed($bank, \parsedBankStatementPayload(), $bank->getParseGeneration());
    $service->fail($bank, 'processing_timeout', $bank->getParseGeneration());
    $job = new ParseBankStatementJob($bank->getKey(), $bank->getParseGeneration());
    $job->handle($service);
    $job->failed(null);
    \expect(BankStatement::query()->find($bank->getKey()))->toBeNull()
        ->and(BankStatementTransaction::query()->find($transaction->getKey()))->toBeNull();
    Storage::disk(FilesystemDiskEnum::Private->value)->assertMissing($bank->getOriginalPath());
    \expect(Storage::disk(FilesystemDiskEnum::Private->value)->files('bank-statement-deletions'))->toBe([]);
})->with(['review', 'confirmed', 'queued', 'processing', 'failed']);

\test('durable deletion markers retry cleanup and never delete a referenced original', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    $bank = BankStatement::factory()->create();
    $disk = Storage::disk(FilesystemDiskEnum::Private->value);
    $path = $bank->getOriginalPath();
    $marker = 'bank-statement-deletions/' . \hash('sha256', $path);
    $disk->put($path, 'encrypted source');
    $disk->put($marker, Resolver::resolveEncrypter()->encryptString($path));
    $service = new BankStatementService();
    $service->cleanupDeletedOriginals();
    $disk->assertExists($path);
    $disk->assertMissing($marker);
    $bank->delete();
    $disk->put($marker, Resolver::resolveEncrypter()->encryptString($path));
    $service->cleanupDeletedOriginals();
    $service->cleanupDeletedOriginals();
    $disk->assertMissing($path);
    $disk->assertMissing($marker);
});

\test('confirmed reanalysis revokes confirmation retains data on failure and allows another attempt', function (): void {
    Queue::fake();
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $bank = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed', 'confirmed_at' => \now(), 'confirmed_by_user_id' => $user->getKey()]);
    $transaction = BankStatementTransaction::factory()->forStatement($bank)->create(['review_note' => 'Keep manual edit']);
    $service = new BankStatementService();
    $oldGeneration = $bank->getParseGeneration();
    $service->retry($bank, $user);
    $bank->refresh();
    \expect($bank->getStatus())->toBe(BankStatementStatusEnum::QUEUED)->and($bank->getParseGeneration())->toBe($oldGeneration + 1);
    $this->assertDatabaseHas('bank_statements', ['id' => $bank->getKey(), 'confirmed_at' => null, 'confirmed_by_user_id' => null]);
    \expect(fn() => $service->retry($bank, $user))->toThrow(InvalidArgumentException::class);
    $service->fail($bank, 'provider_or_parse_failed', $bank->getParseGeneration());
    \expect($bank->fresh()->getStatus())->toBe(BankStatementStatusEnum::REVIEW)
        ->and($transaction->fresh()->getReviewNote())->toBe('Keep manual edit');
    $service->retry($bank->fresh(), $user);
    $bank->refresh();
    $service->applyParsed($bank, \parsedBankStatementPayload(), $oldGeneration);
    \expect($transaction->fresh())->not->toBeNull();
    $service->applyParsed($bank, \parsedBankStatementPayload(), $bank->getParseGeneration());
    \expect($bank->fresh()->getStatus())->toBe(BankStatementStatusEnum::REVIEW)
        ->and($transaction->fresh())->toBeNull();
});

\test('recommendations replace the target reservation and respect other edited rows without writes', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $bank = BankStatement::factory()->forStore($store)->create();
    $daily = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    foreach (\range(1, 5) as $day) {
        StatementDay::factory()->for($daily, 'statement')->create(['date' => \sprintf('2026-08-%02d', $day), 'wolt' => '100.00']);
    }
    $transaction = BankStatementTransaction::factory()->forStatement($bank)->create(['category' => 'wolt', 'amount' => '100.00', 'booked_on' => '2026-08-07', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-05', 'manually_edited' => true]);
    $row = ['id' => $transaction->getKey(), 'category' => 'wolt', 'amount' => '318.50', 'booked_on' => '2026-08-07', 'sales_from' => '2026-08-01', 'sales_to' => '2026-08-05'];
    $service = new BankStatementReconciliationService();
    $result = $service->recommend($bank, $user, [$row], 0);
    \expect($result['candidates'][0])->toMatchArray(['from' => '2026-08-01', 'to' => '2026-08-05', 'reason' => null, 'within_tolerance' => false])
        ->and($transaction->fresh()->getAmount())->toBe('100.00');
    $result = $service->recommend($bank, $user, [$row, [...$row, 'id' => null, 'sales_from' => '2026-08-03', 'sales_to' => '2026-08-03']], 0);
    \expect($result['candidates'][0]['reason'])->toBe('period_conflict');
    $bank->update(['status' => 'confirmed']);
    $result = $service->recommend($bank, $user, [$row], 0);
    \expect($result['candidates'][0]['within_tolerance'])->toBeFalse()->and($transaction->fresh()->getSalesFrom()->toDateString())->toBe('2026-08-01');
});

\test('storage deletion failures keep a durable marker for a later successful retry', function (bool $throw): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    $disk = Storage::disk(FilesystemDiskEnum::Private->value);
    $path = 'bank-statements/deleted-original.pdf.encrypted';
    $marker = 'bank-statement-deletions/' . \hash('sha256', $path);
    $disk->put($path, 'encrypted');
    $disk->put($marker, Resolver::resolveEncrypter()->encryptString($path));
    $canDelete = false;
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('delete')->with($path)->andReturnUsing(static function () use (&$canDelete, $throw, $disk, $path): bool {
        if (!$canDelete && $throw) {
            throw new RuntimeException('storage unavailable');
        }

        return $canDelete && $disk->delete($path);
    });
    $failingDisk->shouldReceive('delete')->with($marker)->andReturnUsing(static fn(): bool => $disk->delete($marker));
    Storage::shouldReceive('disk')->with(FilesystemDiskEnum::Private->value)->andReturn($failingDisk);
    $service = new BankStatementService();
    $service->cleanupDeletedOriginals();
    $disk->assertExists($marker);
    $disk->assertExists($path);
    $canDelete = true;
    $service->cleanupDeletedOriginals();
    $disk->assertMissing($marker);
    $disk->assertMissing($path);
})->with([false, true]);
