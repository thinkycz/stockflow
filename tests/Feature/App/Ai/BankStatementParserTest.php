<?php

declare(strict_types=1);

use App\Ai\Agents\BankStatementParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Files\Document;

\test('PDF requests include the free parser and explicit structured contract', function (string $fixture): void {
    Http::preventStrayRequests();
    Http::fake([
        'openrouter.ai/*' => Http::response([
            'id' => 'synthetic-response',
            'model' => 'minimax/minimax-m3:free',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => \json_encode(\parsedBankStatementPayload(), \JSON_THROW_ON_ERROR)],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 100, 'total_tokens' => 200],
        ]),
    ]);

    $response = (new BankStatementParser())->prompt('Extract the attached statement.', attachments: [
        Document::fromPath(\base_path('tests/Fixtures/bank-statements/' . $fixture))->as($fixture),
    ]);

    \expect($response->toArray())->toBe(\parsedBankStatementPayload());
    Http::assertSent(static function (Request $request) use ($fixture): bool {
        $body = $request->data();
        \expect($body['plugins'])->toBe([['id' => 'file-parser', 'pdf' => ['engine' => 'cloudflare-ai']]])
            ->and($body['max_tokens'])->toBe(24000)
            ->and($body['response_format']['type'])->toBe('json_schema')
            ->and($body['messages'][0]['content'])->toContain('booked_on', 'specific_symbol', 'untrusted data')
            ->and($body['messages'][1]['content'][1]['file']['filename'])->toBe($fixture)
            ->and($body['messages'][1]['content'][1]['file']['file_data'])->toStartWith('data:application/pdf;base64,');

        return true;
    });
})->with(['other-bank.pdf', 'other-bank-scan.pdf']);
