<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
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

\test('Stockflow assistant grounds named business entities in the matching company dataset', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $instructions = (new StockflowAssistant($admin, Str::uuid()->toString()))->instructions();

    \expect($instructions)
        ->toContain('Named Stockflow business entities default to the company\'s saved data')
        ->toContain('For a named recipe or preparation question, call read_recipes with operation=lookup')
        ->toContain('Recipes and recipe categories are company-wide, not active-store scoped')
        ->toContain('A result is complete only for its exact resource, dataset, operation, and applied filters')
        ->toContain('Never use a sibling dataset, such as recipe categories, to claim that a recipe does not exist')
        ->toContain('Do not substitute general knowledge for matching saved data');
});

\test('Stockflow assistant receives authoritative Prague runtime and active store context', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-29 20:56:00', 'UTC'));

    try {
        [$admin] = \createIsolatedUserWithWarehouse();
        $store = Store::factory()->create([
            'user_id' => $admin->getKey(),
            'name' => 'Žižkov',
            'is_warehouse' => false,
        ]);

        $admin->update(['locale' => 'cs']);

        $instructions = (new StockflowAssistant($admin, Str::uuid()->toString(), $store->getKey()))->instructions();

        \expect($instructions)
            ->toContain('The authoritative business date and time is Saturday, 2026-08-29 22:56:00 +02:00 in Europe/Prague.')
            ->toContain('Today is 2026-08-29 and the current business month is 2026-08.')
            ->toContain('The administrator locale is cs.')
            ->toContain('Resolve relative dates such as “today”, “tomorrow”, “yesterday”, “this week”, “this month”, and “this year” from this authoritative snapshot.')
            ->toContain('Convert relative dates to explicit ISO dates and business periods before calling a tool.')
            ->toContain('The active store is Žižkov (#' . $store->getKey() . ', retail, active).');
    } finally {
        CarbonImmutable::setTestNow();
    }
});
