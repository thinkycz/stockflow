<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Models\AssistantTurnEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\test('an administrator can replay only an owned durable turn stream', function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    Config::inject()->assign('ai.assistant.durable_turns', true);
    StockflowAssistant::fake(['Durable replay']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $other = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turnId = Str::uuid()->toString();

    $this->withSession(['_token' => 'assistant-stream-test-token'])
        ->withHeader('X-CSRF-TOKEN', 'assistant-stream-test-token')
        ->be($admin, 'users')
        ->postJson('/assistant/chat', ['message' => 'Replay this turn', 'turn_id' => $turnId])
        ->assertOk();

    $reconnect = $this->be($admin, 'users')->get('/assistant/turns/' . $turnId . '/stream');
    $reconnect->assertOk()->assertHeader('x-assistant-turn-id', $turnId);
    \expect($reconnect->streamedContent())->toContain('Durable');

    $firstSequence = Typer::assertInt(AssistantTurnEvent::query()->where('turn_id', $turnId)->min('sequence'));
    $resumed = $this->be($admin, 'users')
        ->withHeader('Last-Event-ID', (string) $firstSequence)
        ->get('/assistant/turns/' . $turnId . '/stream');
    \expect($resumed->streamedContent())
        ->not->toContain('"type":"start"')
        ->toContain('Durable');

    $this->be($other, 'users')->get('/assistant/turns/' . $turnId . '/stream')->assertNotFound();
});
