<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Models\User;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Config;

\test('Stockflow assistant uses the native OpenRouter MiniMax M3 free provider', function (): void {
    $assistant = new StockflowAssistant(new User(), Str::uuid()->toString());

    \expect(Config::inject()->assertString('ai.providers.openrouter.driver'))->toBe('openrouter')
        ->and($assistant->provider())->toBe('openrouter')
        ->and($assistant->model())->toBe('minimax/minimax-m3:free');
});

\test('Stockflow assistant treats native writer schemas as authoritative', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $instructions = (new StockflowAssistant($admin, Str::uuid()->toString()))->instructions();

    \expect($instructions)
        ->toContain('native writer\'s action schema is authoritative')
        ->toContain('Do not infer additional required fields');
});
