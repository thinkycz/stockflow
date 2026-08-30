<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use Illuminate\Support\Str;
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
