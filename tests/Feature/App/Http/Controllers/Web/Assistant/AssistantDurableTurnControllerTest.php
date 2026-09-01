<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Ai\Agents\StockflowConversationTitleAgent;
use App\Enums\AssistantTurnStatusEnum;
use App\Jobs\RunAssistantTurnJob;
use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Prompts\AgentPrompt;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    Config::inject()->assign('ai.assistant.durable_turns', true);
    StockflowConversationTitleAgent::fake(['Generated conversation title']);
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
    \expect($first->streamedContent())
        ->toContain('"delta":"Durable"')
        ->toContain('"delta":" answer"');

    $second = $this->be($admin, 'users')->postJson('/assistant/chat', [
        ...$payload,
        'conversation_id' => $conversationId,
    ]);

    \expect($second->streamedContent())
        ->toContain('"delta":"Durable"')
        ->toContain('"delta":" answer"')
        ->and(AssistantTurn::query()->count())->toBe(1)
        ->and(AssistantTurnEvent::query()->where('turn_id', $turnId)->whereNull('event_type')->doesntExist())->toBeTrue()
        ->and(AssistantTurnEvent::query()->where('turn_id', $turnId)->latest('sequence')->value('event_type'))->toBe('finish');
    StockflowAssistant::assertPromptedTimes(1);
});

\test('the first completed durable turn replaces the message placeholder with an AI generated title', function (): void {
    StockflowAssistant::fake(['Výdaj byl připraven ke kontrole.', 'Doplnění bylo zaznamenáno.']);
    StockflowConversationTitleAgent::fake(['## “Nákup výrobníku ledu.”']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turnId = Str::uuid()->toString();

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Přidej dnes jednorázový výdaj za výrobník ledu na Žižkově za 34 303 Kč',
        'turn_id' => $turnId,
    ]);
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));

    $response->assertOk();
    $response->streamedContent();

    \expect($admin->conversations()->whereKey($conversationId)->value('title'))
        ->toBe('Nákup výrobníku ledu');
    StockflowConversationTitleAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        return \str_contains($prompt->prompt, 'Přidej dnes jednorázový výdaj') &&
            \str_contains($prompt->prompt, 'Výdaj byl připraven ke kontrole.');
    });

    $followUp = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversationId,
        'message' => 'Ještě doplň poznámku k nákupu',
        'turn_id' => Str::uuid()->toString(),
    ]);
    $followUp->streamedContent();

    \expect($admin->conversations()->whereKey($conversationId)->value('title'))
        ->toBe('Nákup výrobníku ledu');
    StockflowConversationTitleAgent::assertPromptedTimes(1);
});

\test('optional title generation failure keeps the placeholder without failing the durable turn', function (): void {
    StockflowAssistant::fake(['Hotovo.']);
    StockflowConversationTitleAgent::fake(static function (): never {
        throw new RuntimeException('Optional title provider failure');
    });
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $turnId = Str::uuid()->toString();
    $message = 'Zkontroluj dnešní směny na Žižkově';

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => $message,
        'turn_id' => $turnId,
    ]);
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));
    $response->streamedContent();

    $turn = Typer::assertInstance(AssistantTurn::query()->whereKey($turnId)->first(), AssistantTurn::class);
    \expect($turn->getStatus())->toBe(AssistantTurnStatusEnum::COMPLETED)
        ->and($admin->conversations()->whereKey($conversationId)->value('title'))->toBe($message);
});

\test('derived context maintenance cannot downgrade a natively persisted assistant response', function (): void {
    StockflowAssistant::fake(['This canonical answer must remain visible.']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    StockflowConversationTitleAgent::fake(static function () use ($admin): string {
        $conversation = Typer::assertInstance(
            $admin->conversations()->latest('created_at')->first(),
            Conversation::class,
        );
        $conversation->messages()->create([
            'id' => Str::uuid()->toString(),
            'participant_type' => $admin->getMorphClass(),
            'participant_id' => $admin->getKey(),
            'agent' => StockflowAssistant::class,
            'role' => 'assistant',
            'content' => '',
            'attachments' => [],
            'tool_calls' => [[
                'id' => 'malformed-post-persistence-call',
                'name' => 'read_recipes',
                'arguments' => ['request' => ['operation' => 'list']],
            ]],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);

        return 'Persisted response';
    });
    $turnId = Str::uuid()->toString();

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Keep the completed answer',
        'turn_id' => $turnId,
    ]);
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));
    $stream = $response->streamedContent();
    $turn = Typer::assertInstance(AssistantTurn::query()->whereKey($turnId)->first(), AssistantTurn::class);
    $conversation = Typer::assertInstance(
        $admin->conversations()->whereKey($conversationId)->first(),
        Conversation::class,
    );

    \expect($stream)->not->toContain('AI assistant generation failed.')
        ->and($turn->getStatus())->toBe(AssistantTurnStatusEnum::COMPLETED)
        ->and($conversation->messages()
            ->where('role', 'assistant')
            ->where('content', 'This canonical answer must remain visible.')
            ->exists())->toBeTrue();
});

\test('queued turns hydrate their optimistic user message and can be cancelled by their owner', function (): void {
    Queue::fake();
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->createOne([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $turnId = Str::uuid()->toString();
    $response = $this->be($admin, 'users')->withSession(\activeStoreSession($store))->postJson('/assistant/chat', [
        'message' => 'Keep this visible',
        'turn_id' => $turnId,
    ]);
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));

    Queue::assertPushed(
        RunAssistantTurnJob::class,
        static fn(RunAssistantTurnJob $job): bool => $job instanceof ShouldBeEncrypted &&
        $job->turnId === $turnId &&
        $job->activeStoreId === $store->getKey() &&
        $job->browserSessionId !== null,
    );

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
