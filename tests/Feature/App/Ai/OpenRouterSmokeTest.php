<?php

declare(strict_types=1);

use App\Ai\Agents\BankStatementParser;
use App\Ai\Agents\StockflowAssistant;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Thinkycz\LaravelCore\Support\Env;

\test('OpenRouter MiniMax M3 free selects concrete native read and writer tools', function (): void {
    if (Env::inject()->parseBool('OPENROUTER_SMOKE_TEST') !== true) {
        $this->markTestSkipped('Set OPENROUTER_SMOKE_TEST=true to run the live provider smoke test.');
    }

    [$admin] = \createIsolatedUserWithWarehouse();
    $read = (new StockflowAssistant($admin, Str::uuid()->toString()))
        ->prompt('Use read_shifts with limit 1, then briefly say whether any shifts exist.');
    $recipe = (new StockflowAssistant($admin, Str::uuid()->toString()))
        ->prompt('Jak se dělá Oolong Milk Tea podle našeho uloženého receptu?');
    $write = (new StockflowAssistant($admin, Str::uuid()->toString()))
        ->prompt('Propose creating worker Leo Do at hourly rate 130. Do not ask for optional values.');

    \expect($read->toolCalls->pluck('name')->all())->toContain('read_shifts')
        ->and($recipe->toolCalls->pluck('name')->all())->toContain('read_recipes')
        ->and($write->hasPendingApprovals())->toBeTrue()
        ->and($write->pendingApprovals->sole()->tool)->toBe('write_workers')
        ->and($write->pendingApprovals->sole()->arguments)->toMatchArray([
            'request' => [
                'action' => 'create_worker',
                'values' => ['first_name' => 'Leo', 'last_name' => 'Do', 'hourly_rate' => 130],
            ],
        ]);
});

\test('OpenRouter parses a synthetic anonymized Czech Savings Bank PDF', function (): void {
    if (Env::inject()->parseBool('OPENROUTER_SMOKE_TEST') !== true) {
        $this->markTestSkipped('Set OPENROUTER_SMOKE_TEST=true to run the live provider smoke test.');
    }

    $pdf = <<<'PDF'
        %PDF-1.4
        1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
        2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
        3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj
        4 0 obj<</Length 303>>stream
        BT /F1 10 Tf 40 740 Td (Ceska sporitelna - account statement 8/2026) Tj 0 -18 Td (Bank code: 0800 Currency: CZK Period: 2026-08-01 to 2026-08-31) Tj 0 -18 Td (Opening balance: 100.00 Total credits: 99.00 Total debits: 0.00 Closing balance: 199.00) Tj 0 -18 Td (2026-08-02 Card payout +99.00 CZK specific symbol 20260801) Tj ET
        endstream endobj
        5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj
        trailer<</Root 1 0 R>>
        %%EOF
        PDF;
    $response = (new BankStatementParser())->prompt(
        'Parse this synthetic statement.',
        attachments: [Document::fromString($pdf, 'application/pdf')->as('synthetic-cs-statement.pdf')],
    );

    \expect($response['bank_code'])->toBe('0800')
        ->and($response['currency'])->toBe('CZK')
        ->and($response['transactions'])->toHaveCount(1);
});
