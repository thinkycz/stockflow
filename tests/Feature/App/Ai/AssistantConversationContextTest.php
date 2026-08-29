<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Ai\AssistantConversationContext;
use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\AssistantTurn;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

\test('conversation context expands beyond one hundred rows and preserves complete recent turns', function (): void {
    Config::inject()->assign('ai.assistant.context_max_rows', 300);
    Config::inject()->assign('ai.assistant.context_max_characters', 500000);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Long conversation',
    ]), Conversation::class);

    foreach (\range(1, 1151) as $turn) {
        foreach (['user', 'assistant'] as $role) {
            $conversation->messages()->create([
                'id' => Str::ulid()->toString(),
                'participant_type' => $admin->getMorphClass(),
                'participant_id' => $admin->getKey(),
                'agent' => StockflowAssistant::class,
                'role' => $role,
                'content' => "{$role} turn {$turn}",
                'attachments' => [],
                'tool_calls' => [],
                'tool_results' => [],
                'usage' => [],
                'meta' => [],
            ]);
        }
    }

    $context = new AssistantConversationContext();
    $messages = $context->recentMessages(Typer::assertString($conversation->getKey()));

    \expect($messages)->toHaveCount(300)
        ->and($messages[0]->role->value)->toBe('user')
        ->and($messages[299]->content)->toBe('assistant turn 1151');

    $context->refreshSummary($conversation);
    \expect($context->summary(Typer::assertString($conversation->getKey())))
        ->toContain('Administrator: user turn 1')
        ->not->toContain('assistant turn 1');

    Config::inject()->assign('ai.assistant.context_max_characters', 50);
    $bounded = $context->recentMessages(Typer::assertString($conversation->getKey()));

    \expect($bounded)->toHaveCount(2)
        ->and($bounded[0]->content)->toBe('user turn 1151')
        ->and($bounded[1]->content)->toBe('assistant turn 1151');
});

\test('rolling memory derives rejected actions from audit state instead of tool result presence', function (): void {
    Config::inject()->assign('ai.assistant.context_max_rows', 2);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Rejected action memory',
    ]), Conversation::class);

    foreach ([
        ['user', 'Please delete the shift', [], []],
        ['assistant', '', [['id' => 'rejected-call', 'name' => 'write_shifts', 'arguments' => []]], [['id' => 'rejected-call', 'result' => 'Action rejected']]],
        ['user', 'Keep it instead', [], []],
        ['assistant', 'Understood', [], []],
    ] as [$role, $content, $calls, $results]) {
        $conversation->messages()->create([
            'id' => Str::ulid()->toString(),
            'participant_type' => $admin->getMorphClass(),
            'participant_id' => $admin->getKey(),
            'agent' => StockflowAssistant::class,
            'role' => $role,
            'content' => $content,
            'attachments' => [],
            'tool_calls' => $calls,
            'tool_results' => $results,
            'usage' => [],
            'meta' => [],
        ]);
    }
    AssistantActionAudit::factory()->createOne([
        'actor_user_id' => $admin->getKey(),
        'conversation_id' => $conversation->getKey(),
        'tool_call_id' => 'rejected-call',
        'tool_name' => 'write_shifts',
        'classification' => AssistantActionClassificationEnum::MUTATION->value,
        'status' => AssistantActionStatusEnum::REJECTED->value,
    ]);

    $context = new AssistantConversationContext();
    $context->refreshSummary($conversation);
    $summary = Typer::assertString($context->summary(Typer::assertString($conversation->getKey())));

    \expect($summary)->toContain('Rejected action: write_shifts')
        ->not->toContain('Completed action: write_shifts');
});

\test('older context is summarized without copying raw tool result values', function (): void {
    Config::inject()->assign('ai.assistant.context_max_rows', 2);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Summarized conversation',
    ]), Conversation::class);

    foreach (['Remember the staffing preference', 'Acknowledged', 'Current question', 'Current answer'] as $index => $content) {
        $conversation->messages()->create([
            'id' => Str::ulid()->toString(),
            'participant_type' => $admin->getMorphClass(),
            'participant_id' => $admin->getKey(),
            'agent' => StockflowAssistant::class,
            'role' => $index % 2 === 0 ? 'user' : 'assistant',
            'content' => $content,
            'attachments' => [],
            'tool_calls' => $index === 1 ? [['id' => 'call-1', 'name' => 'write_shifts', 'arguments' => []]] : [],
            'tool_results' => $index === 1 ? [['id' => 'call-1', 'result' => 'SECRET LIVE VALUE']] : [],
            'usage' => [],
            'meta' => [],
        ]);
    }
    AssistantActionAudit::factory()->createOne([
        'actor_user_id' => $admin->getKey(),
        'conversation_id' => $conversation->getKey(),
        'tool_call_id' => 'call-1',
        'tool_name' => 'write_shifts',
        'classification' => AssistantActionClassificationEnum::MUTATION->value,
        'status' => AssistantActionStatusEnum::SUCCEEDED->value,
    ]);

    $context = new AssistantConversationContext();
    $context->refreshSummary($conversation);
    $summary = $context->summary(Typer::assertString($conversation->getKey()));

    \expect($summary)->toContain('Remember the staffing preference')
        ->toContain('Completed action: write_shifts')
        ->not->toContain('SECRET LIVE VALUE');
});

\test('a retry sends its prompt once instead of duplicating the persisted logical input in model context', function (): void {
    Config::inject()->assign('ai.assistant.context_max_rows', 300);
    Config::inject()->assign('ai.assistant.context_max_characters', 500000);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Retry context',
    ]), Conversation::class);
    $conversation->messages()->create([
        'id' => Str::ulid()->toString(),
        'participant_type' => $admin->getMorphClass(),
        'participant_id' => $admin->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'user',
        'content' => 'Audit all recipes',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);
    $payload = ['message' => 'Audit all recipes'];
    $root = AssistantTurn::query()->forceCreate([
        'id' => Str::uuid()->toString(),
        'actor_user_id' => $admin->getKey(),
        'conversation_id' => $conversation->getKey(),
        'parent_turn_id' => null,
        'kind' => 'message',
        'recovery_mode' => 'normal',
        'status' => AssistantTurnStatusEnum::FAILED->value,
        'input_hash' => \hash('sha256', \json_encode($payload, \JSON_THROW_ON_ERROR)),
        'input_payload' => $payload,
        'queued_at' => \now()->subMinute(),
        'completed_at' => \now()->subMinute(),
    ]);
    $retry = AssistantTurn::query()->forceCreate([
        'id' => Str::uuid()->toString(),
        'actor_user_id' => $admin->getKey(),
        'conversation_id' => $conversation->getKey(),
        'parent_turn_id' => $root->getTurnId(),
        'kind' => 'message',
        'recovery_mode' => 'replay_without_action',
        'status' => AssistantTurnStatusEnum::RUNNING->value,
        'input_hash' => \hash('sha256', \json_encode($payload, \JSON_THROW_ON_ERROR)),
        'input_payload' => $payload,
        'queued_at' => \now(),
        'started_at' => \now(),
    ]);
    Context::add('assistant_turn_id', $retry->getTurnId());

    try {
        $messages = (new AssistantConversationContext())->recentMessages(Typer::assertString($conversation->getKey()));
    } finally {
        Context::forget('assistant_turn_id');
    }

    \expect($messages)->toBe([]);
});

\test('stored parallel tool calls require exactly one result or one unresolved approval', function (): void {
    Config::inject()->assign('ai.assistant.context_max_rows', 300);
    Config::inject()->assign('ai.assistant.context_max_characters', 500000);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Parallel read integrity',
    ]), Conversation::class);
    $conversation->messages()->create([
        'id' => Str::ulid()->toString(),
        'participant_type' => $admin->getMorphClass(),
        'participant_id' => $admin->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'user',
        'content' => 'Compare staffing and revenue.',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);
    $assistant = $conversation->messages()->create([
        'id' => Str::ulid()->toString(),
        'participant_type' => $admin->getMorphClass(),
        'participant_id' => $admin->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [
            ['id' => 'read-staffing', 'name' => 'read_shifts', 'arguments' => []],
            ['id' => 'read-revenue', 'name' => 'read_statements', 'arguments' => []],
        ],
        'tool_results' => [
            ['id' => 'read-staffing', 'name' => 'read_shifts', 'arguments' => [], 'result' => '{}'],
            ['id' => 'read-revenue', 'name' => 'read_statements', 'arguments' => [], 'result' => '{}'],
        ],
        'usage' => [],
        'meta' => [],
    ]);
    $context = new AssistantConversationContext();

    \expect(fn(): array => $context->recentMessages(Typer::assertString($conversation->getKey())))
        ->not->toThrow(RuntimeException::class);

    $assistant->update(['tool_results' => [
        ['id' => 'read-staffing', 'name' => 'read_shifts', 'arguments' => [], 'result' => '{}'],
    ]]);
    \expect(fn(): array => $context->recentMessages(Typer::assertString($conversation->getKey())))
        ->toThrow(RuntimeException::class, 'incomplete tool interaction');

    $assistant->update(['approval_state' => ['pending' => ['read-revenue' => 'Approval required']]]);
    \expect(fn(): array => $context->recentMessages(Typer::assertString($conversation->getKey())))
        ->not->toThrow(RuntimeException::class);

    $assistant->update([
        'approval_state' => null,
        'tool_results' => [
            ['id' => 'read-staffing', 'name' => 'read_shifts', 'arguments' => [], 'result' => '{}'],
            ['id' => 'read-staffing', 'name' => 'read_shifts', 'arguments' => [], 'result' => '{}'],
            ['id' => 'read-revenue', 'name' => 'read_statements', 'arguments' => [], 'result' => '{}'],
        ],
    ]);
    \expect(fn(): array => $context->recentMessages(Typer::assertString($conversation->getKey())))
        ->toThrow(RuntimeException::class, 'incomplete tool interaction');
});
