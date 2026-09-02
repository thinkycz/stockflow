<?php

declare(strict_types=1);

use App\Services\BankStatementIntegrityService;

\test('parsed bank statement totals and balance equation are verified exactly', function (): void {
    $service = new BankStatementIntegrityService();

    \expect($service->warnings(\parsedBankStatementPayload()))->toBe([]);

    $invalid = \parsedBankStatementPayload();
    $invalid['closing_balance'] = '850.01';
    $invalid['credit_count'] = 2;

    \expect($service->warnings($invalid))->toContain('balance_mismatch', 'credit_count_mismatch');
});
