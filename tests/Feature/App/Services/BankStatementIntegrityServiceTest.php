<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementIntegrityService;
use App\Exceptions\InvalidBankStatementPayloadException;

\test('parsed bank statement totals and balance equation are verified exactly', function (): void {
    $service = new BankStatementIntegrityService();

    \expect($service->warnings(\parsedBankStatementPayload()))->toBe([]);

    $invalid = \parsedBankStatementPayload();
    $invalid['closing_balance'] = '850.01';
    $invalid['credit_count'] = 2;

    \expect($service->warnings($invalid))->toContain('balance_mismatch', 'credit_count_mismatch');
});

\test('parsed money accepts surrounding whitespace but rejects excess precision', function (): void {
    $service = new BankStatementIntegrityService();
    $payload = \parsedBankStatementPayload();
    $payload['transactions'][0]['amount'] = ' 1000.00 ';

    \expect($service->warnings($payload))->toBe([]);

    $payload['transactions'][0]['amount'] = '1000.001';

    \expect(fn() => $service->warnings($payload))
        ->toThrow(InvalidBankStatementPayloadException::class);
});

\test('parsed available balance rejects excess precision', function (): void {
    $service = new BankStatementIntegrityService();
    $payload = \parsedBankStatementPayload();
    $payload['available_balance'] = ' 850.001 ';

    \expect(fn() => $service->validateParsedPayload($payload))
        ->toThrow(InvalidBankStatementPayloadException::class);
});

\test('parsed dates categories and required fields are validated before persistence', function (string $path, mixed $value): void {
    $service = new BankStatementIntegrityService();
    $payload = \parsedBankStatementPayload();
    \data_set($payload, $path, $value);

    \expect(fn() => $service->validateParsedPayload($payload))
        ->toThrow(InvalidBankStatementPayloadException::class);
})->with([
    ['period_from', '2026-02-30'],
    ['transactions.0.booked_on', 'not-a-date'],
    ['transactions.0.category', 'invented'],
    ['transactions.0.item_type', null],
    ['bank_code', '12345678901234567'],
    ['credit_count', 'not-an-integer'],
    ['debit_count', 1.5],
    ['available_balance', false],
    ['account_name', 123],
    ['transactions.0.executed_on', false],
    ['transactions.0.description', 123],
    ['transactions.0.item_type', 'This item type deliberately exceeds the one-hundred-and-sixty-character database column used by parsed statement transaction rows so validation catches it before persistence fails.'],
    ['transactions.0.amount', '10000000000000.00'],
]);

\test('encrypted text limits are enforced in bytes for multibyte parser values', function (): void {
    $payload = \parsedBankStatementPayload();
    $payload['transactions'][0]['counterparty_name'] = \str_repeat('ž', 16001);

    \expect(fn() => (new BankStatementIntegrityService())->validateParsedPayload($payload))
        ->toThrow(InvalidBankStatementPayloadException::class);
});

\test('missing bank metadata and printed summaries remain optional', function (): void {
    $payload = \parsedBankStatementPayload();
    foreach (['bank_code', 'statement_number', 'total_credits', 'total_debits', 'credit_count', 'debit_count'] as $key) {
        $payload[$key] = null;
    }
    $service = new BankStatementIntegrityService();
    $service->validateParsedPayload($payload);
    \expect($service->warnings($payload))->toBe([]);
    $payload['closing_balance'] = '850.01';
    \expect($service->warnings($payload))->toContain('balance_mismatch');
});

\test('transaction currencies cannot bypass CZK confirmation checks', function (): void {
    $payload = \parsedBankStatementPayload();
    $payload['transactions'][0]['currency'] = 'EUR';
    \expect((new BankStatementIntegrityService())->warnings($payload))->toContain('unsupported_currency');
});

\test('card sales dates are derived from valid symbols independently of AI date fields', function (string $symbol, string|null $expected): void {
    $payload = \parsedBankStatementPayload();
    $payload['transactions'][0]['specific_symbol'] = $symbol;
    $payload['transactions'][0]['sales_from'] = null;
    $payload['transactions'][0]['sales_to'] = null;
    $normalized = (new BankStatementIntegrityService())->normalizeParsedPayload($payload);
    \expect($normalized['transactions'][0]['sales_from'])->toBe($expected)
        ->and($normalized['transactions'][0]['sales_to'])->toBe($expected);
})->with([
    ['20260731', '2026-07-31'],
    ['20260230', null],
    ['0903660002', null],
]);
