<?php

declare(strict_types=1);

use App\Enums\BankStatementStatusEnum;
use App\Enums\FilesystemDiskEnum;
use App\Jobs\ParseBankStatementJob;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\StatementDay;
use App\Models\Store;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('bank statement section is admin only and scoped to the active store', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $statement = BankStatement::factory()->forStore($store)->create();

    $this->be($limited, 'users')->get('/bank-statements')->assertRedirect();
    $this->withSession(\activeStoreSession($otherStore));
    $this->be($admin, 'users')->get('/bank-statements/' . $statement->getKey())->assertNotFound();
});

\test('admin uploads one real PDF privately and exact duplicates reuse the existing import', function (): void {
    Queue::fake();
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $this->withSession(\activeStoreSession($store));
    $pdf = static fn(): UploadedFile => UploadedFile::fake()->createWithContent('statement.pdf', "%PDF-1.7\nsynthetic statement");

    $this->be($admin, 'users')->post('/bank-statements', ['document' => $pdf()])->assertRedirect();
    $this->be($admin, 'users')->post('/bank-statements', ['document' => $pdf()])->assertRedirect();
    $this->withSession(\activeStoreSession($otherStore));
    $duplicate = $this->be($admin, 'users')->post('/bank-statements', ['document' => $pdf()]);

    \expect(BankStatement::query()->count())->toBe(1);
    $statement = Typer::assertInstance(BankStatement::query()->first(), BankStatement::class);
    $duplicate->assertRedirect('/bank-statements/' . $statement->getKey())
        ->assertSessionHas('active_store_id', $store->getKey());
    Storage::disk(FilesystemDiskEnum::Private->value)->assertExists($statement->getOriginalPath());
    \expect(Storage::disk(FilesystemDiskEnum::Private->value)->get($statement->getOriginalPath()))
        ->not->toContain('%PDF');
    Queue::assertPushed(ParseBankStatementJob::class, 1);
});

\test('upload rejects non PDF files and oversized PDF files', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $this->withSession(\activeStoreSession($store));

    $this->be($admin, 'users')->withHeader('Accept', 'application/json')->post('/bank-statements', [
        'document' => UploadedFile::fake()->create('statement.txt', 1, 'text/plain'),
    ])->assertUnprocessable()->assertJsonValidationErrors('document');
    $this->be($admin, 'users')->withHeader('Accept', 'application/json')->post('/bank-statements', [
        'document' => UploadedFile::fake()->create('statement.pdf', 10241, 'application/pdf'),
    ])->assertUnprocessable()->assertJsonValidationErrors('document');
});

\test('private original requires the active store and disables caching', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $statement = BankStatement::factory()->forStore($store)->create(['original_path' => 'bank-statements/secret.pdf']);
    Storage::disk(FilesystemDiskEnum::Private->value)->put(
        $statement->getOriginalPath(),
        Resolver::resolveEncrypter()->encryptString('%PDF-secret'),
    );
    $this->withSession(\activeStoreSession($store));

    $this->be($admin, 'users')->get('/bank-statements/' . $statement->getKey() . '/original')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertStreamedContent('%PDF-secret');
    $this->withSession(\activeStoreSession($otherStore));
    $this->be($admin, 'users')->get('/bank-statements/' . $statement->getKey() . '/original')->assertNotFound();
});

\test('review can be edited confirmed and reopened without changing daily reports', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $this->withSession(\activeStoreSession($store));
    $statement = BankStatement::factory()->forStore($store)->create([
        'total_credits' => '99.00',
        'closing_balance' => '199.00',
        'parse_warnings' => [],
    ]);
    $day = StatementDay::factory()->create(['card' => '100.00', 'total' => '100.00']);
    $payload = [
        'transactions' => [[
            'booked_on' => '2026-08-02',
            'executed_on' => null,
            'item_type' => 'Card payout',
            'amount' => '99.00',
            'currency' => 'CZK',
            'counterparty_name' => 'Processor',
            'counterparty_account' => null,
            'variable_symbol' => null,
            'constant_symbol' => null,
            'specific_symbol' => '20260801',
            'description' => null,
            'category' => 'card',
            'sales_from' => '2026-08-01',
            'sales_to' => '2026-08-01',
            'review_note' => null,
        ]],
    ];

    $this->be($admin, 'users')->put('/bank-statements/' . $statement->getKey(), $payload)->assertRedirect();
    $this->be($admin, 'users')->post('/bank-statements/' . $statement->getKey() . '/confirm')->assertRedirect();
    \expect($statement->fresh()?->getStatus())->toBe(BankStatementStatusEnum::CONFIRMED)
        ->and(BankStatementTransaction::query()->count())->toBe(1)
        ->and($day->fresh()?->getCard())->toBe(100.0);
    $this->be($admin, 'users')->withHeader('Accept', 'application/json')
        ->put('/bank-statements/' . $statement->getKey(), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('statement');
    $this->be($admin, 'users')->post('/bank-statements/' . $statement->getKey() . '/reopen')->assertRedirect();
    \expect($statement->fresh()?->getStatus())->toBe(BankStatementStatusEnum::REVIEW);
});

\test('confirmation is blocked by integrity warnings and missing sales periods', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $this->withSession(\activeStoreSession($store));
    $statement = BankStatement::factory()->forStore($store)->create([
        'parse_warnings' => ['balance_mismatch'],
    ]);

    $this->be($admin, 'users')->withHeader('Accept', 'application/json')
        ->post('/bank-statements/' . $statement->getKey() . '/confirm')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('statement');
    \expect($statement->fresh()?->getStatus())->toBe(BankStatementStatusEnum::REVIEW);

    $statement->update(['parse_warnings' => []]);
    BankStatementTransaction::factory()->forStatement($statement)->create([
        'category' => 'wolt',
        'sales_from' => null,
        'sales_to' => null,
    ]);

    $this->be($admin, 'users')->withHeader('Accept', 'application/json')
        ->post('/bank-statements/' . $statement->getKey() . '/confirm')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('statement');
    \expect($statement->fresh()?->getStatus())->toBe(BankStatementStatusEnum::REVIEW);
});
