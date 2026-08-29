<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Enums\AssistantTurnStatusEnum;
use App\Jobs\RunAssistantTurnJob;
use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    Config::inject()->assign('ai.assistant.durable_turns', true);
    $this->withSession(['_token' => 'assistant-durable-test-token'])
        ->withHeader('X-CSRF-TOKEN', 'assistant-durable-test-token');
});

\test('a durable turn is journaled and duplicate submissions replay without prompting twice', function (): void {
    StockflowAssistant::fake(['Durable answer']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turnId = Str::uuid()->toString();
    $payload = ['message' => 'Remember this', 'turn_id' => $turnId];

    $first = $this->be($admin, 'users')->postJson('/assistant/chat', $payload);
    $conversationId = Typer::assertString($first->headers->get('x-conversation-id'));

    $first->assertOk()->assertHeader('x-assistant-turn-id', $turnId);
    \expect($first->streamedContent())->toContain('"delta":"Durable answer"');

    $second = $this->be($admin, 'users')->postJson('/assistant/chat', [
        ...$payload,
        'conversation_id' => $conversationId,
    ]);

    \expect($second->streamedContent())
        ->toContain('"delta":"Durable answer"')
        ->and(AssistantTurn::query()->count())->toBe(1)
        ->and(AssistantTurnEvent::query()->where('turn_id', $turnId)->whereNull('event_type')->doesntExist())->toBeTrue()
        ->and(AssistantTurnEvent::query()->where('turn_id', $turnId)->latest('sequence')->value('event_type'))->toBe('finish');
    StockflowAssistant::assertPromptedTimes(1);
});

\test('queued turns hydrate their optimistic user message and can be cancelled by their owner', function (): void {
    Queue::fake();
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turnId = Str::uuid()->toString();
    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Keep this visible',
        'turn_id' => $turnId,
    ]);
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));

    Queue::assertPushed(RunAssistantTurnJob::class, static fn(RunAssistantTurnJob $job): bool => $job->turnId === $turnId);

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversationId, $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.active_turn.id', $turnId)
        ->assertJsonPath('props.conversation.active_turn.message', 'Keep this visible');

    $this->be($admin, 'users')
        ->post('/assistant/turns/' . $turnId . '/cancel')
        ->assertNoContent();

    $turn = Typer::assertInstance(AssistantTurn::query()->whereKey($turnId)->first(), AssistantTurn::class);
    \expect($turn->getStatus())->toBe(AssistantTurnStatusEnum::CANCEL_REQUESTED);
});

\test('turn admission atomically permits only one active turn per conversation', function (): void {
    Queue::fake();
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $firstTurnId = Str::uuid()->toString();
    $first = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'First queued request',
        'turn_id' => $firstTurnId,
    ]);
    $conversationId = Typer::assertString($first->headers->get('x-conversation-id'));

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversationId,
        'message' => 'Competing request',
        'turn_id' => Str::uuid()->toString(),
    ])->assertConflict();

    \expect(AssistantTurn::query()->where('conversation_id', $conversationId)->count())->toBe(1);
    Queue::assertPushed(RunAssistantTurnJob::class, 1);
});

\test('reconnect streams are ownership scoped', function (): void {
    StockflowAssistant::fake(['Reconnectable']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $other = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turnId = Str::uuid()->toString();

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Continue despite disconnect',
        'turn_id' => $turnId,
    ]);

    $reconnect = $this->be($admin, 'users')->get('/assistant/turns/' . $turnId . '/stream');
    $reconnect->assertOk()->assertHeader('x-assistant-turn-id', $turnId);
    \expect($reconnect->streamedContent())->toContain('"delta":"Reconnectable"');

    $this->be($other, 'users')->get('/assistant/turns/' . $turnId . '/stream')->assertNotFound();
});

\test('provider failures become durable failed turns with a replayable safe error', function (): void {
    StockflowAssistant::fake(static function (): never {
        throw new RuntimeException('Provider secret detail');
    });
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turnId = Str::uuid()->toString();
    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Fail durably',
        'turn_id' => $turnId,
    ]);

    \expect($response->streamedContent())
        ->toContain('AI assistant generation failed.')
        ->not->toContain('Provider secret detail');
    $turn = Typer::assertInstance(AssistantTurn::query()->whereKey($turnId)->first(), AssistantTurn::class);
    \expect($turn->getStatus())->toBe(AssistantTurnStatusEnum::FAILED)
        ->and($turn->getInputPayload())->toBe(['message' => 'Fail durably']);
});
