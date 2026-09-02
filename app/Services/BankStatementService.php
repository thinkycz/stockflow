<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BankStatementStatusEnum;
use App\Enums\BankStatementTransactionCategoryEnum;
use App\Enums\FilesystemDiskEnum;
use App\Jobs\ParseBankStatementJob;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class BankStatementService
{
    /**
     * Store an original document and queue its first parsing attempt.
     *
     * @return array{statement: BankStatement, created: bool}
     */
    public function upload(User $actor, Store $store, UploadedFile $file): array
    {
        $sha256 = \hash_file('sha256', $file->getPathname());

        if ($sha256 === false) {
            throw new RuntimeException('Bank statement checksum could not be calculated.');
        }

        $existing = BankStatement::query()
            ->where('user_id', $actor->getKey())
            ->where('sha256', $sha256)
            ->first();

        if ($existing instanceof BankStatement) {
            return ['statement' => $existing, 'created' => false];
        }

        $path = 'bank-statements/' . $actor->getKey() . '/' . $store->getKey() . '/' . Str::random(40) . '.pdf.encrypted';
        $stored = Resolver::resolveFilesystemManager()
            ->disk(FilesystemDiskEnum::Private->value)
            ->put($path, Resolver::resolveEncrypter()->encryptString($file->getContent()));

        if (!$stored) {
            throw new RuntimeException('Bank statement could not be stored.');
        }

        try {
            $statement = BankStatement::query()->create([
                'user_id' => $actor->getKey(),
                'store_id' => $store->getKey(),
                'uploaded_by_user_id' => $actor->getKey(),
                'status' => BankStatementStatusEnum::QUEUED->value,
                'original_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'original_mime' => 'application/pdf',
                'original_size' => $file->getSize(),
                'sha256' => $sha256,
                'attempt_count' => 0,
                'queued_at' => \now(),
            ]);
        } catch (Throwable $throwable) {
            Resolver::resolveFilesystemManager()->disk(FilesystemDiskEnum::Private->value)->delete($path);
            throw $throwable;
        }

        \dispatch(new ParseBankStatementJob($statement->getKey()));

        return ['statement' => $statement, 'created' => true];
    }

    /**
     * Decrypt an archived original into memory for an authorized consumer.
     */
    public function originalContents(BankStatement $statement): string
    {
        $encrypted = Resolver::resolveFilesystemManager()
            ->disk(FilesystemDiskEnum::Private->value)
            ->get($statement->getOriginalPath());

        return Resolver::resolveEncrypter()->decryptString(Typer::assertString($encrypted));
    }

    /**
     * Find one statement within the authenticated company scope.
     */
    public function findOwned(User $actor, int $id): BankStatement
    {
        $query = BankStatement::query();
        BankStatement::scopeForUser($query, $actor->resolveScopeUser());

        return Typer::assertInstance($query->whereKey($id)->firstOrFail(), BankStatement::class);
    }

    /**
     * Replace the current unconfirmed draft with validated parser output.
     *
     * @param array<string, mixed> $payload
     */
    public function applyParsed(BankStatement $statement, array $payload): void
    {
        $warnings = (new BankStatementIntegrityService())->warnings($payload);

        if (Typer::assertString($payload['bank_code'] ?? null) !== '0800') {
            $warnings[] = 'unsupported_bank';
        }

        if (Typer::assertString($payload['currency'] ?? null) !== 'CZK') {
            $warnings[] = 'unsupported_currency';
        }

        $logicalDuplicate = BankStatement::query()
            ->where('user_id', $statement->getUserId())
            ->where('store_id', $statement->getStoreId())
            ->where('bank_code', Typer::assertString($payload['bank_code'] ?? null))
            ->where('statement_number', Typer::assertString($payload['statement_number'] ?? null))
            ->whereDate('period_from', Typer::assertString($payload['period_from'] ?? null))
            ->whereDate('period_to', Typer::assertString($payload['period_to'] ?? null))
            ->whereKeyNot($statement->getKey())
            ->exists();

        if ($logicalDuplicate) {
            $statement->update([
                'status' => BankStatementStatusEnum::FAILED->value,
                'last_error' => 'duplicate_statement',
                'parsed_at' => \now(),
            ]);

            return;
        }

        DB::transaction(function () use ($statement, $payload, $warnings): void {
            $statement->transactions()->delete();

            $position = 0;

            foreach (Typer::assertArray($payload['transactions'] ?? []) as $transaction) {
                $row = Typer::assertStringKeyArray(Typer::assertArray($transaction));
                ++$position;
                $statement->transactions()->create([
                    ...$this->transactionAttributes($row),
                    'position' => $position,
                    'source_payload' => $row,
                    'manually_edited' => false,
                ]);
            }

            $statement->update([
                'status' => BankStatementStatusEnum::REVIEW->value,
                'bank_code' => Typer::assertString($payload['bank_code'] ?? null),
                'bank_name' => Typer::assertString($payload['bank_name'] ?? null),
                'account_name' => Typer::parseNullableString($payload['account_name'] ?? null),
                'account_number' => Typer::parseNullableString($payload['account_number'] ?? null),
                'iban' => Typer::parseNullableString($payload['iban'] ?? null),
                'bic' => Typer::parseNullableString($payload['bic'] ?? null),
                'currency' => Typer::assertString($payload['currency'] ?? null),
                'statement_number' => Typer::assertString($payload['statement_number'] ?? null),
                'period_from' => Typer::assertString($payload['period_from'] ?? null),
                'period_to' => Typer::assertString($payload['period_to'] ?? null),
                'opening_balance' => Typer::assertString($payload['opening_balance'] ?? null),
                'total_credits' => Typer::assertString($payload['total_credits'] ?? null),
                'total_debits' => Typer::assertString($payload['total_debits'] ?? null),
                'closing_balance' => Typer::assertString($payload['closing_balance'] ?? null),
                'available_balance' => Typer::parseNullableString($payload['available_balance'] ?? null),
                'credit_count' => Typer::parseInt($payload['credit_count'] ?? null),
                'debit_count' => Typer::parseInt($payload['debit_count'] ?? null),
                'parse_warnings' => \array_values(\array_unique($warnings)),
                'raw_ai_response' => $payload,
                'last_error' => null,
                'parsed_at' => \now(),
            ]);
        });
    }

    /**
     * Replace editable transaction rows with the administrator-reviewed draft.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function updateDraft(BankStatement $statement, array $rows): void
    {
        if ($statement->getStatus() !== BankStatementStatusEnum::REVIEW) {
            throw new InvalidArgumentException('statement_not_editable');
        }

        DB::transaction(function () use ($statement, $rows): void {
            $existingById = [];

            foreach ($statement->getTransactions() as $existing) {
                $existingById[$existing->getKey()] = $existing;
            }

            $statement->transactions()->delete();

            foreach ($rows as $position => $row) {
                $existingId = Typer::parseNullableInt($row['id'] ?? null);
                $existing = $existingId === null ? null : ($existingById[$existingId] ?? null);
                $statement->transactions()->create([
                    ...$this->transactionAttributes($row, $existing),
                    'position' => $position + 1,
                    'source_payload' => null,
                    'manually_edited' => true,
                ]);
            }

            $warnings = (new BankStatementIntegrityService())->warnings([
                'opening_balance' => $statement->getOpeningBalance(),
                'total_credits' => $statement->getTotalCredits(),
                'total_debits' => $statement->getTotalDebits(),
                'closing_balance' => $statement->getClosingBalance(),
                'credit_count' => $statement->getCreditCount(),
                'debit_count' => $statement->getDebitCount(),
                'transactions' => $rows,
            ]);

            if ($statement->getBankCode() !== '0800') {
                $warnings[] = 'unsupported_bank';
            }

            if ($statement->getCurrency() !== 'CZK') {
                $warnings[] = 'unsupported_currency';
            }

            $statement->update(['parse_warnings' => $warnings]);
        });
    }

    /**
     * Confirm a structurally valid reviewed statement.
     */
    public function confirm(BankStatement $statement, User $actor): void
    {
        if ($statement->getStatus() !== BankStatementStatusEnum::REVIEW) {
            throw new InvalidArgumentException('statement_not_confirmable');
        }

        if ($statement->getParseWarnings() !== []) {
            throw new InvalidArgumentException('statement_integrity_failed');
        }

        foreach ($statement->getTransactions() as $transaction) {
            if ($transaction->getCategory()->reconciliable() &&
                ($transaction->getSalesFrom() === null || $transaction->getSalesTo() === null)
            ) {
                throw new InvalidArgumentException('statement_period_missing');
            }
        }

        $statement->update([
            'status' => BankStatementStatusEnum::CONFIRMED->value,
            'confirmed_by_user_id' => $actor->getKey(),
            'confirmed_at' => \now(),
        ]);
    }

    /**
     * Return a confirmed statement to editable review.
     */
    public function reopen(BankStatement $statement, User $actor): void
    {
        if ($statement->getStatus() !== BankStatementStatusEnum::CONFIRMED) {
            throw new InvalidArgumentException('statement_not_reopenable');
        }

        $statement->update([
            'status' => BankStatementStatusEnum::REVIEW->value,
            'reopened_by_user_id' => $actor->getKey(),
            'reopened_at' => \now(),
            'confirmed_by_user_id' => null,
            'confirmed_at' => null,
        ]);
    }

    /**
     * Queue a new parsing attempt without discarding the current draft first.
     */
    public function retry(BankStatement $statement): void
    {
        if (!\in_array($statement->getStatus(), [BankStatementStatusEnum::REVIEW, BankStatementStatusEnum::FAILED], true)) {
            throw new InvalidArgumentException('statement_not_retryable');
        }

        $statement->update([
            'status' => BankStatementStatusEnum::QUEUED->value,
            'last_error' => null,
            'queued_at' => \now(),
        ]);

        \dispatch(new ParseBankStatementJob($statement->getKey()));
    }

    /**
     * Mark a parsing attempt as failed with a non-sensitive error key.
     */
    public function fail(BankStatement $statement, string $error): void
    {
        $statement->update([
            'status' => BankStatementStatusEnum::FAILED->value,
            'last_error' => $error,
            'parsed_at' => \now(),
        ]);
    }

    /**
     * Normalize transaction attributes shared by parser and manual review.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, bool|string|null>
     */
    private function transactionAttributes(
        array $row,
        BankStatementTransaction|null $existing = null,
    ): array
    {
        $category = BankStatementTransactionCategoryEnum::from(Typer::assertString($row['category'] ?? null));

        return [
            'booked_on' => Typer::assertString($row['booked_on'] ?? null),
            'executed_on' => Typer::parseNullableString($row['executed_on'] ?? null),
            'item_type' => Typer::assertString($row['item_type'] ?? null),
            'amount' => Typer::assertString($row['amount'] ?? null),
            'currency' => Typer::assertString($row['currency'] ?? null),
            'counterparty_name' => Typer::parseNullableString($row['counterparty_name'] ?? null),
            'counterparty_account' => $existing instanceof BankStatementTransaction
                ? $existing->getCounterpartyAccount()
                : Typer::parseNullableString($row['counterparty_account'] ?? null),
            'variable_symbol' => Typer::parseNullableString($row['variable_symbol'] ?? null),
            'constant_symbol' => Typer::parseNullableString($row['constant_symbol'] ?? null),
            'specific_symbol' => Typer::parseNullableString($row['specific_symbol'] ?? null),
            'description' => Typer::parseNullableString($row['description'] ?? null),
            'category' => $category->value,
            'sales_from' => Typer::parseNullableString($row['sales_from'] ?? null),
            'sales_to' => Typer::parseNullableString($row['sales_to'] ?? null),
            'review_note' => Typer::parseNullableString($row['review_note'] ?? null),
        ];
    }
}
