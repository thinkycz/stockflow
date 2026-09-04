<?php

declare(strict_types=1);

namespace App\Domain\BankStatements;

use App\Enums\BankStatementTransactionCategoryEnum;
use App\Exceptions\InvalidBankStatementPayloadException;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class BankStatementIntegrityService
{
    private const int MAX_ENCRYPTED_TEXT_INPUT = 32000;

    /**
     * Validate every parser field before any draft row is replaced.
     *
     * @param array<string, mixed> $payload
     */
    public function validateParsedPayload(array $payload): void
    {
        try {
            $this->boundedString($payload['bank_code'] ?? null, 16);
            $this->boundedString($payload['bank_name'] ?? null, 120);
            $this->boundedString($payload['currency'] ?? null, 3);
            $this->boundedString($payload['statement_number'] ?? null, 32);
            $this->boundedNullableString($payload['bic'] ?? null, 32);

            foreach (['account_name', 'account_number', 'iban'] as $key) {
                $this->boundedNullableString($payload[$key] ?? null, self::MAX_ENCRYPTED_TEXT_INPUT);
            }

            $periodFrom = Typer::assertString($payload['period_from'] ?? null);
            $periodTo = Typer::assertString($payload['period_to'] ?? null);
            $this->date($periodFrom);
            $this->date($periodTo);
            if ($periodFrom > $periodTo) {
                throw new InvalidBankStatementPayloadException('Bank statement period is invalid.');
            }

            foreach (['opening_balance', 'total_credits', 'total_debits', 'closing_balance'] as $key) {
                $this->money(Typer::assertString($payload[$key] ?? null));
            }

            $availableBalance = Typer::assertNullableString($payload['available_balance'] ?? null);
            if ($availableBalance !== null) {
                $this->money($availableBalance);
            }

            foreach (['credit_count', 'debit_count'] as $key) {
                $count = Typer::assertInt($payload[$key] ?? null);
                if ($count < 0 || $count > 4294967295) {
                    throw new InvalidBankStatementPayloadException('Bank statement counts cannot be negative.');
                }
            }

            foreach (Typer::assertArray($payload['transactions'] ?? null) as $transaction) {
                $row = Typer::assertStringKeyArray(Typer::assertArray($transaction));
                $this->date(Typer::assertString($row['booked_on'] ?? null));
                $this->nullableDate($row['executed_on'] ?? null);
                $this->nullableDate($row['sales_from'] ?? null);
                $this->nullableDate($row['sales_to'] ?? null);
                $this->money(Typer::assertString($row['amount'] ?? null));
                BankStatementTransactionCategoryEnum::from(Typer::assertString($row['category'] ?? null));

                $this->boundedString($row['item_type'] ?? null, 160);
                $this->boundedString($row['currency'] ?? null, 3);

                foreach (['counterparty_name', 'counterparty_account', 'variable_symbol', 'constant_symbol', 'specific_symbol', 'description', 'review_note'] as $key) {
                    $this->boundedNullableString($row[$key] ?? null, self::MAX_ENCRYPTED_TEXT_INPUT);
                }
            }
        } catch (InvalidBankStatementPayloadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidBankStatementPayloadException('Bank statement parser payload is invalid.', previous: $exception);
        }
    }

    /**
     * Return structural warning keys for parsed statement data.
     *
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    public function warnings(array $payload): array
    {
        $transactions = Typer::assertArray($payload['transactions'] ?? []);
        $credits = BigDecimal::zero();
        $debits = BigDecimal::zero();
        $creditCount = 0;
        $debitCount = 0;

        foreach ($transactions as $transaction) {
            $row = Typer::assertStringKeyArray(Typer::assertArray($transaction));
            $amount = $this->money(Typer::assertString($row['amount'] ?? null));

            if ($amount->isPositive()) {
                $credits = $credits->plus($amount);
                ++$creditCount;
            } elseif ($amount->isNegative()) {
                $debits = $debits->plus($amount->abs());
                ++$debitCount;
            }
        }

        $warnings = [];

        if ($creditCount !== Typer::assertInt($payload['credit_count'] ?? null)) {
            $warnings[] = 'credit_count_mismatch';
        }

        if ($debitCount !== Typer::assertInt($payload['debit_count'] ?? null)) {
            $warnings[] = 'debit_count_mismatch';
        }

        if (!$credits->isEqualTo($this->money(Typer::assertString($payload['total_credits'] ?? null)))) {
            $warnings[] = 'credit_sum_mismatch';
        }

        if (!$debits->isEqualTo($this->money(Typer::assertString($payload['total_debits'] ?? null)))) {
            $warnings[] = 'debit_sum_mismatch';
        }

        $calculatedClosing = $this->money(Typer::assertString($payload['opening_balance'] ?? null))
            ->plus($credits)
            ->minus($debits);

        if (!$calculatedClosing->isEqualTo($this->money(Typer::assertString($payload['closing_balance'] ?? null)))) {
            $warnings[] = 'balance_mismatch';
        }

        $availableBalance = Typer::assertNullableString($payload['available_balance'] ?? null);
        if ($availableBalance !== null) {
            $this->money($availableBalance);
        }

        return $warnings;
    }

    /**
     * Validate an optional ISO calendar date.
     */
    private function nullableDate(mixed $value): void
    {
        $value = Typer::assertNullableString($value);
        if ($value !== null) {
            $this->date($value);
        }
    }

    /**
     * Validate an exact ISO calendar date.
     */
    private function date(string $value): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable || $value !== $date->format('Y-m-d')) {
            throw new InvalidBankStatementPayloadException('Bank statement dates must be valid ISO calendar dates.');
        }
    }

    /**
     * Require a string that fits its persistence column.
     */
    private function boundedString(mixed $value, int $maxLength): string
    {
        $value = Typer::assertString($value);
        if ($maxLength < \mb_strlen($value)) {
            throw new InvalidBankStatementPayloadException('Bank statement parser text exceeds its allowed length.');
        }

        return $value;
    }

    /**
     * Require optional text that fits its encrypted persistence column.
     */
    private function boundedNullableString(mixed $value, int $maxLength): string|null
    {
        $value = Typer::assertNullableString($value);
        if ($value !== null && $maxLength < \mb_strlen($value, '8bit')) {
            throw new InvalidBankStatementPayloadException('Bank statement parser text exceeds its allowed length.');
        }

        return $value;
    }

    /**
     * Normalize a decimal value to cents without floating point arithmetic.
     */
    private function money(string $value): BigDecimal
    {
        try {
            $amount = BigDecimal::of(\trim($value))->toScale(2, RoundingMode::Unnecessary);
            if ($amount->abs()->isGreaterThan(BigDecimal::of('9999999999999.99'))) {
                throw new InvalidBankStatementPayloadException('Bank statement money exceeds its allowed range.');
            }

            return $amount;
        } catch (MathException $exception) {
            throw new InvalidBankStatementPayloadException('Bank statement money must use exact two-decimal precision.', previous: $exception);
        }
    }
}
