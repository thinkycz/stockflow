<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementService;
use App\Enums\FilesystemDiskEnum;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Store;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

\test('bank mutations reject limited and foreign administrator callers before persistence or dispatch', function (string $role, string $operation): void {
    Queue::fake();
    [$owner] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $owner->getKey()]);
    $actor = $role === 'limited' ? UserFactory::new()->limited($store)->createOne() : UserFactory::new()->admin()->createOne();
    $statement = BankStatement::factory()->forStore($store)->create(['status' => $operation === 'reopen' ? 'confirmed' : 'review']);
    BankStatementTransaction::factory()->forStatement($statement)->create();
    $before = $statement->fresh()?->getRawOriginal();
    $rowsBefore = $statement->transactions()->get()->map(static fn(BankStatementTransaction $row): array => $row->getRawOriginal())->all();
    $service = new BankStatementService();

    try {
        match ($operation) {
            'edit' => $service->updateDraft($statement, [], $actor),
            'retry' => $service->retry($statement, $actor),
            'confirm' => $service->confirm($statement, $actor),
            'reopen' => $service->reopen($statement, $actor),
        };
        $this->fail('Unauthorized bank mutation succeeded.');
    } catch (HttpException $exception) {
        \expect($exception->getStatusCode())->toBe(404);
    }

    \expect($statement->fresh()?->getRawOriginal())->toBe($before)
        ->and($statement->transactions()->get()->map(static fn(BankStatementTransaction $row): array => $row->getRawOriginal())->all())->toBe($rowsBefore);
    Queue::assertNothingPushed();
})->with(['limited', 'foreign'])->with(['edit', 'retry', 'confirm', 'reopen']);

\test('bank upload refuses non-owner administrators and limited accounts before storing private files', function (string $role): void {
    Queue::fake();
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$owner] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $owner->getKey()]);
    $actor = $role === 'limited' ? UserFactory::new()->limited($store)->createOne() : UserFactory::new()->admin()->createOne();
    $file = UploadedFile::fake()->createWithContent('statement.pdf', '%PDF-1.7 test');

    \expect(fn() => (new BankStatementService())->upload($actor, $store, $file))->toThrow(HttpException::class)
        ->and(BankStatement::query()->count())->toBe(0)
        ->and(Storage::disk(FilesystemDiskEnum::Private->value)->allFiles())->toBe([]);
    Queue::assertNothingPushed();
})->with(['limited', 'foreign']);
