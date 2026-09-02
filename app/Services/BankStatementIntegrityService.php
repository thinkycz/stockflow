<?php

declare(strict_types=1);

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Thinkycz\LaravelCore\Support\Typer;

final class BankStatementIntegrityService
{
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

        if ($creditCount !== Typer::parseInt($payload['credit_count'] ?? null)) {
            $warnings[] = 'credit_count_mismatch';
        }

        if ($debitCount !== Typer::parseInt($payload['debit_count'] ?? null)) {
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

        return $warnings;
    }

    /**
     * Normalize a decimal value to cents without floating point arithmetic.
     */
    private function money(string $value): BigDecimal
    {
        return BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
    }
}
