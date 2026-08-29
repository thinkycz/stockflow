<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\AssistantTurn;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    Config::inject()->assign('ai.assistant.durable_turns', true);
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
        ->and($retry->getStatus())->toBe(AssistantTurnStatusEnum::COMPLETED);

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversationId, $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.active_turn', null);
});

\test('retry after a completed mutation creates continuation only recovery', function (): void {
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
        'classification' => AssistantActionClassificationEnum::MUTATION->value,
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
});
