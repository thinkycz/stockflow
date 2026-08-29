<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\AssistantTurn;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

function createAssistantHydrationTurn(
    User $actor,
    Conversation $conversation,
    AssistantTurnStatusEnum $status,
    Carbon $createdAt,
    string $message,
    string|null $parentTurnId = null,
): AssistantTurn {
    $payload = ['message' => $message];

    return AssistantTurn::query()->forceCreate([
        'id' => Str::uuid()->toString(),
        'actor_user_id' => $actor->getKey(),
        'conversation_id' => $conversation->getKey(),
        'parent_turn_id' => $parentTurnId,
        'kind' => 'message',
        'recovery_mode' => $parentTurnId === null ? 'normal' : 'replay_without_action',
        'status' => $status->value,
        'input_hash' => \hash('sha256', \json_encode($payload, \JSON_THROW_ON_ERROR)),
        'input_payload' => $status->terminal() && $status !== AssistantTurnStatusEnum::FAILED ? [] : $payload,
        'error_summary' => $status === AssistantTurnStatusEnum::FAILED ? 'Temporary provider failure' : null,
        'queued_at' => $createdAt,
        'started_at' => $status === AssistantTurnStatusEnum::QUEUED ? null : $createdAt,
        'completed_at' => $status->terminal() ? $createdAt : null,
        'cancel_requested_at' => \in_array($status, [AssistantTurnStatusEnum::CANCEL_REQUESTED, AssistantTurnStatusEnum::CANCELLED], true) ? $createdAt : null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

\beforeEach(function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
});

\test('guest is redirected from the assistant to login', function (): void {
    $this->get('/assistant')->assertRedirect('/login');
});

\test('limited user cannot access the assistant', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $this->be($limited, 'users')->get('/assistant')->assertRedirect('/dashboard');
});

\test('main admin can open a new assistant conversation page', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->be($admin, 'users')->get('/assistant', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'assistant/Index');
    $response->assertJsonPath('props.conversation', null);
    $response->assertJsonPath('props.conversations', []);
    $response->assertJsonMissingPath('props.privacy');
});

\test('main admin can load only an owned assistant conversation', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $owned = $admin->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Owned']);
    $foreign = $otherAdmin->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Foreign']);

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $owned->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.id', $owned->getKey())
        ->assertJsonPath('props.conversation.title', 'Owned');

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $foreign->getKey(), $this->inertiaHeaders())
        ->assertNotFound();
});

\test('owned conversation hydrates its latest failed turn for safe retry', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Latest failure',
    ]), Conversation::class);
    $failed = \createAssistantHydrationTurn(
        $admin,
        $conversation,
        AssistantTurnStatusEnum::FAILED,
        Carbon::parse('2026-08-29 22:03:30'),
        'Please retry this',
    );

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversation->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.active_turn.id', $failed->getTurnId())
        ->assertJsonPath('props.conversation.active_turn.status', AssistantTurnStatusEnum::FAILED->value)
        ->assertJsonPath('props.conversation.active_turn.message', 'Please retry this')
        ->assertJsonPath('props.conversation.active_turn.can_retry', true);
});

\test('newer non-recoverable turns suppress an older failed turn', function (AssistantTurnStatusEnum $status): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Resolved failure',
    ]), Conversation::class);
    \createAssistantHydrationTurn(
        $admin,
        $conversation,
        AssistantTurnStatusEnum::FAILED,
        Carbon::parse('2026-08-29 22:03:30'),
        'Old failed input',
    );
    \createAssistantHydrationTurn(
        $admin,
        $conversation,
        $status,
        Carbon::parse('2026-08-29 22:04:30'),
        'Newer resolved input',
    );

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversation->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.active_turn', null);
})->with([
    'awaiting approval' => AssistantTurnStatusEnum::AWAITING_APPROVAL,
    'completed' => AssistantTurnStatusEnum::COMPLETED,
    'cancelled' => AssistantTurnStatusEnum::CANCELLED,
]);

\test('a newer failed retry remains available after an older failure', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Repeated failure',
    ]), Conversation::class);
    $original = \createAssistantHydrationTurn(
        $admin,
        $conversation,
        AssistantTurnStatusEnum::FAILED,
        Carbon::parse('2026-08-29 22:03:30'),
        'Original failed input',
    );
    $retry = \createAssistantHydrationTurn(
        $admin,
        $conversation,
        AssistantTurnStatusEnum::FAILED,
        Carbon::parse('2026-08-29 22:04:30'),
        'Retried input',
        $original->getTurnId(),
    );

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversation->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.active_turn.id', $retry->getTurnId())
        ->assertJsonPath('props.conversation.active_turn.message', 'Retried input');
});

\test('latest active durable turns remain resumable', function (AssistantTurnStatusEnum $status): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = Typer::assertInstance($admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Active turn',
    ]), Conversation::class);
    \createAssistantHydrationTurn(
        $admin,
        $conversation,
        AssistantTurnStatusEnum::COMPLETED,
        Carbon::parse('2026-08-29 22:03:30'),
        'Completed input',
    );
    $active = \createAssistantHydrationTurn(
        $admin,
        $conversation,
        $status,
        Carbon::parse('2026-08-29 22:04:30'),
        'Active input',
    );

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversation->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.active_turn.id', $active->getTurnId())
        ->assertJsonPath('props.conversation.active_turn.status', $status->value);
})->with([
    'queued' => AssistantTurnStatusEnum::QUEUED,
    'running' => AssistantTurnStatusEnum::RUNNING,
    'cancel requested' => AssistantTurnStatusEnum::CANCEL_REQUESTED,
]);

\test('owned conversation messages include their persisted creation timestamp', function (): void {
    Carbon::setTestNow('2026-08-29 09:42:15');
    $createdAt = Carbon::now()->toJSON();
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = $admin->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Timestamped']);
    $conversation->messages()->create([
        'id' => Str::uuid()->toString(),
        'participant_type' => $admin->getMorphClass(),
        'participant_id' => $admin->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'user',
        'content' => 'When was this sent?',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversation->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.messages.0.metadata.created_at', $createdAt);
});

\test('owned conversation reload hydrates persisted pending approval state', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = $admin->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Pending']);
    $conversation->messages()->create([
        'id' => Str::uuid()->toString(),
        'participant_type' => $admin->getMorphClass(),
        'participant_id' => $admin->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [[
            'id' => 'pending-tool',
            'name' => 'write_inventory_counts',
            'arguments' => [
                'request' => ['action' => 'start_inventory_draft', 'store_id' => 10],
            ],
        ]],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'approval_state' => ['pending' => ['pending-tool' => 'Start the count draft?']],
    ]);

    $response = $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversation->getKey(), $this->inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('props.conversation.messages.0.parts.0.state', 'approval-requested')
        ->assertJsonPath('props.conversation.messages.0.parts.0.toolCallId', 'pending-tool')
        ->assertJsonPath('props.conversation.messages.0.parts.0.approval.requestReason', 'Start the count draft?');
});

\test('owned conversation reload hydrates a persisted clarification choice', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = $admin->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Choice']);
    $options = [
        ['id' => 'brno', 'label' => 'Brno'],
        ['id' => 'ostrava', 'label' => 'Ostrava'],
    ];
    $preview = \json_encode([
        'version' => 1,
        'kind' => 'choice',
        'question' => 'Which store?',
        'options' => $options,
    ], \JSON_THROW_ON_ERROR);
    $conversation->messages()->create([
        'id' => Str::uuid()->toString(),
        'participant_type' => $admin->getMorphClass(),
        'participant_id' => $admin->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [[
            'id' => 'pending-choice',
            'name' => 'ask_user_choice',
            'arguments' => ['question' => 'Which store?', 'options' => $options],
        ]],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'approval_state' => ['pending' => ['pending-choice' => $preview]],
    ]);

    $this->be($admin, 'users')
        ->get('/assistant/conversations/' . $conversation->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.conversation.messages.0.parts.0.type', 'tool-ask_user_choice')
        ->assertJsonPath('props.conversation.messages.0.parts.0.state', 'approval-requested')
        ->assertJsonPath('props.conversation.messages.0.parts.0.approval.requestReason', $preview);
});

\test('deleting a conversation retains its independent assistant action audit', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = $admin->conversations()->create(['id' => Str::uuid()->toString(), 'title' => 'Delete me']);
    $audit = AssistantActionAudit::factory()->create([
        'actor_user_id' => $admin->getKey(),
        'actor_email' => $admin->getEmail(),
        'conversation_id' => $conversation->getKey(),
    ]);

    $this->be($admin, 'users')
        ->delete('/assistant/conversations/' . $conversation->getKey())
        ->assertRedirect('/assistant');

    \expect(Conversation::query()->find($conversation->getKey()))->toBeNull()
        ->and(AssistantActionAudit::query()->find($audit->getKey()))->not->toBeNull();
});

\test('disabled assistant returns not found for an admin', function (): void {
    Config::inject()->assign('ai.assistant.enabled', false);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $this->be($admin, 'users')->get('/assistant')->assertNotFound();
});
