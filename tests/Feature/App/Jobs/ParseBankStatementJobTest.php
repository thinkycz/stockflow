<?php

declare(strict_types=1);

use App\Ai\Agents\BankStatementParser;
use App\Enums\BankStatementStatusEnum;
use App\Enums\FilesystemDiskEnum;
use App\Jobs\ParseBankStatementJob;
use App\Models\BankStatement;
use App\Models\Store;
use App\Services\BankStatementService;
use Illuminate\Support\Facades\Storage;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('encrypted parser job replaces the draft from a structured fake response', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    Storage::disk(FilesystemDiskEnum::Private->value)->put(
        'bank-statements/test.pdf',
        Resolver::resolveEncrypter()->encryptString('%PDF-1.7 test'),
    );
    $statement = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::QUEUED->value,
        'original_path' => 'bank-statements/test.pdf',
        'attempt_count' => 0,
    ]);
    $statement->transactions()->create([
        'position' => 1,
        'booked_on' => '2026-08-01',
        'item_type' => 'Old row',
        'amount' => '1.00',
        'currency' => 'CZK',
        'category' => 'other_incoming',
    ]);
    BankStatementParser::fake([\parsedBankStatementPayload()]);

    $job = new ParseBankStatementJob($statement->getKey());
    $job->handle(new BankStatementService());
    $job->handle(new BankStatementService());

    $fresh = Typer::assertInstance($statement->fresh(), BankStatement::class);
    \expect($fresh->getStatus())->toBe(BankStatementStatusEnum::REVIEW)
        ->and($fresh->getAttemptCount())->toBe(1)
        ->and($fresh->getParseWarnings())->toBe([])
        ->and($fresh->transactions()->count())->toBe(2)
        ->and($fresh->transactions()->orderBy('position')->first()?->getAttribute('item_type'))->toBe('Incoming transfer');
    BankStatementParser::assertPromptedTimes(1);
});

\test('provider failures become a safe failed state without leaking the exception', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [, $store] = \createIsolatedUserWithWarehouse();
    Storage::disk(FilesystemDiskEnum::Private->value)->put(
        'bank-statements/test.pdf',
        Resolver::resolveEncrypter()->encryptString('%PDF-1.7 test'),
    );
    $statement = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::QUEUED->value,
        'original_path' => 'bank-statements/test.pdf',
        'attempt_count' => 0,
    ]);
    BankStatementParser::fake(static function (): never {
        throw new RuntimeException('secret provider response');
    });

    (new ParseBankStatementJob($statement->getKey()))->handle(new BankStatementService());

    $fresh = Typer::assertInstance($statement->fresh(), BankStatement::class);
    \expect($fresh->getStatus())->toBe(BankStatementStatusEnum::FAILED)
        ->and($fresh->getLastError())->toBe('provider_or_parse_failed');
});

\test('invalid parser money is classified separately from provider failures', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [, $store] = \createIsolatedUserWithWarehouse();
    Storage::disk(FilesystemDiskEnum::Private->value)->put(
        'bank-statements/test.pdf',
        Resolver::resolveEncrypter()->encryptString('%PDF-1.7 test'),
    );
    $statement = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::QUEUED->value,
        'original_path' => 'bank-statements/test.pdf',
    ]);
    $payload = \parsedBankStatementPayload();
    $payload['transactions'][0]['amount'] = '1000.001';
    BankStatementParser::fake([$payload]);

    (new ParseBankStatementJob($statement->getKey()))->handle(new BankStatementService());

    \expect($statement->fresh()?->getStatus())->toBe(BankStatementStatusEnum::FAILED)
        ->and($statement->fresh()?->getLastError())->toBe('invalid_parser_payload');
});

\test('malformed structured parser fields are classified as invalid payloads', function (string $path, mixed $value): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [, $store] = \createIsolatedUserWithWarehouse();
    Storage::disk(FilesystemDiskEnum::Private->value)->put(
        'bank-statements/test.pdf',
        Resolver::resolveEncrypter()->encryptString('%PDF-1.7 test'),
    );
    $statement = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::QUEUED->value,
        'original_path' => 'bank-statements/test.pdf',
    ]);
    $payload = \parsedBankStatementPayload();
    \data_set($payload, $path, $value);
    BankStatementParser::fake([$payload]);

    (new ParseBankStatementJob($statement->getKey()))->handle(new BankStatementService());

    \expect($statement->fresh()?->getStatus())->toBe(BankStatementStatusEnum::FAILED)
        ->and($statement->fresh()?->getLastError())->toBe('invalid_parser_payload');
})->with([
    ['period_to', '2026-02-30'],
    ['transactions.0.category', 'invented'],
    ['transactions.0.item_type', null],
    ['bank_code', '12345678901234567'],
    ['credit_count', 'not-an-integer'],
    ['debit_count', 1.5],
    ['available_balance', false],
    ['transactions.0.executed_on', false],
    ['transactions.0.amount', '10000000000000.00'],
]);

\test('a second parsed copy of the same logical statement is rejected', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $attributes = [
        'status' => BankStatementStatusEnum::QUEUED->value,
        'bank_code' => null,
        'statement_number' => null,
        'period_from' => null,
        'period_to' => null,
    ];
    $first = BankStatement::factory()->forStore($store)->create($attributes);
    $second = BankStatement::factory()->forStore($store)->create($attributes);
    $service = new BankStatementService();

    $service->applyParsed($first, \parsedBankStatementPayload());
    $service->applyParsed($second, \parsedBankStatementPayload());

    \expect($first->fresh()?->getStatus())->toBe(BankStatementStatusEnum::REVIEW)
        ->and($second->fresh()?->getStatus())->toBe(BankStatementStatusEnum::FAILED)
        ->and($second->fresh()?->getLastError())->toBe('duplicate_statement');
});

\test('an exhausted parser job leaves no active processing row', function (): void {
    [, $store] = \createIsolatedUserWithWarehouse();
    $statement = BankStatement::factory()->forStore($store)->create([
        'status' => BankStatementStatusEnum::PROCESSING->value,
    ]);

    (new ParseBankStatementJob($statement->getKey()))->failed(null);

    \expect($statement->fresh()?->getStatus())->toBe(BankStatementStatusEnum::FAILED)
        ->and($statement->fresh()?->getLastError())->toBe('processing_timeout');
});
