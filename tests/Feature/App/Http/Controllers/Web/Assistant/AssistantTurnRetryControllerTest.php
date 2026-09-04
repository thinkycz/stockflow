<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Ai\Agents\StockflowConversationTitleAgent;
use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\AssistantTurn;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    Config::inject()->assign('ai.assistant.durable_turns', true);
    StockflowConversationTitleAgent::fake(['Generated conversation title']);
    $this->withSession(['_token' => 'assistant-retry-test-token'])
        ->withHeader('X-CSRF-TOKEN', 'assistant-retry-test-token');
});

\test('failed turns retry as idempotent child turns', function (): void {
    StockflowAssistant::fake(static function (): never {
        throw new RuntimeException('Temporary provider failure');
    });
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $failedTurnId = Str::uuid()->toString();
    $failedResponse = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Analyze current stock',
        'turn_id' => $failedTurnId,
    ]);
    $failedResponse->streamedContent();
    $conversationId = Typer::assertString($failedResponse->headers->get('x-conversation-id'));
    $conversation = Typer::assertInstance(
        $admin->conversations()->whereKey($conversationId)->first(),
        Conversation::class,
    );
    $conversation->messages()->create([
        'id' => Str::uuid()->toString(),
        'participant_type' => $admin->getMorphClass(),
        'participant_id' => $admin->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'user',
        'content' => 'Analyze current stock',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);
    $this->travel(1)->seconds();

    StockflowAssistant::fake(['Recovered safely']);
    $retryTurnId = Str::uuid()->toString();
    $this->be($admin, 'users')->postJson('/assistant/turns/' . $failedTurnId . '/retry', [
        'turn_id' => $retryTurnId,
    ])->assertAccepted()->assertJson([
        'turn_id' => $retryTurnId,
        'recovery_mode' => 'replay_without_action',
    ]);

    $retry = Typer::assertInstance(AssistantTurn::query()->whereKey($retryTurnId)->first(), AssistantTurn::class);
    \expect($retry->getParentTurnId())->toBe($failedTurnId)
        ->and($retry->getRecoveryMode())->toBe('replay_without_action')
        ->and($retry->getStatus())->toBe(AssistantTurnStatusEnum::COMPLETED)
        ->and($conversation->messages()->where('role', 'user')->where('content', 'Analyze current stock')->count())->toBe(1);

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversationId, $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.active_turn', null);
});

\test('retry after a completed action creates continuation only recovery', function (AssistantActionClassificationEnum $classification): void {
    StockflowAssistant::fake(static function (): never {
        throw new RuntimeException('Post-action generation failure');
    });
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $failedTurnId = Str::uuid()->toString();
    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Complete an action',
        'turn_id' => $failedTurnId,
    ]);
    $response->streamedContent();
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));
    AssistantActionAudit::factory()->createOne([
        'actor_user_id' => $admin->getKey(),
        'conversation_id' => $conversationId,
        'turn_id' => $failedTurnId,
        'classification' => $classification->value,
        'status' => AssistantActionStatusEnum::SUCCEEDED->value,
        'result_summary' => ['message' => 'Action completed'],
    ]);

    StockflowAssistant::fake(['The previous action completed.']);
    $retryTurnId = Str::uuid()->toString();
    $this->be($admin, 'users')->postJson('/assistant/turns/' . $failedTurnId . '/retry', [
        'turn_id' => $retryTurnId,
    ])->assertAccepted()->assertJson([
        'turn_id' => $retryTurnId,
        'recovery_mode' => 'continuation_after_action',
    ]);

    $retry = Typer::assertInstance(AssistantTurn::query()->whereKey($retryTurnId)->first(), AssistantTurn::class);
    \expect($retry->getParentTurnId())->toBe($failedTurnId)
        ->and($retry->getRecoveryMode())->toBe('continuation_after_action')
        ->and($retry->getStatus())->toBe(AssistantTurnStatusEnum::COMPLETED);
})->with([AssistantActionClassificationEnum::MUTATION, AssistantActionClassificationEnum::EXTERNAL_SIDE_EFFECT]);

\test('uncertain external outcomes explain verification and reject turn retries', function (): void {
    StockflowAssistant::fake(static function (): never {
        throw new RuntimeException('Uncertain external outcome');
    });
    $admin = UserFactory::new()->admin()->createOne();
    $turnId = Str::uuid()->toString();
    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Send the Slack test', 'turn_id' => $turnId,
    ]);
    $response->streamedContent();
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));
    AssistantActionAudit::factory()->createOne([
        'actor_user_id' => $admin->getKey(), 'conversation_id' => $conversationId, 'turn_id' => $turnId,
        'classification' => AssistantActionClassificationEnum::EXTERNAL_SIDE_EFFECT->value,
        'status' => AssistantActionStatusEnum::UNCERTAIN->value,
    ]);
    $this->get('/assistant/conversations/' . $conversationId, $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.conversation.active_turn.can_retry', false)
        ->assertJsonPath('props.conversation.active_turn.failure.code', 'ACTION_OUTCOME_UNCERTAIN')
        ->assertJsonPath('props.conversation.active_turn.failure.message', 'The external outcome is uncertain. Verify the result before taking further action; this turn cannot be retried.');
    $retryId = Str::uuid()->toString();
    $this->postJson('/assistant/turns/' . $turnId . '/retry', ['turn_id' => $retryId])->assertConflict();
    \expect(AssistantTurn::query()->whereKey($retryId)->exists())->toBeFalse();
});
