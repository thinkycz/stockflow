<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Config::inject()->assign('ai.assistant.enabled', true);
    $this->withSession(['_token' => 'assistant-chat-test-token'])
        ->withHeader('X-CSRF-TOKEN', 'assistant-chat-test-token');
});

\test('guest cannot stream assistant chat', function (): void {
    $this->postJson('/assistant/chat', ['message' => 'Hello'])->assertUnauthorized();
});

\test('limited user cannot stream assistant chat', function (): void {
    [, $store] = \createIsolatedUserWithWarehouse();
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);

    $this->be($limited, 'users')->postJson('/assistant/chat', ['message' => 'Hello'])->assertRedirect('/dashboard');
});

\test('main admin can stream a new remembered assistant conversation', function (): void {
    StockflowAssistant::fake(['Hello from Stockflow']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Hello',
        'conversation_id' => null,
    ]);

    $response->assertOk()
        ->assertHeader('x-vercel-ai-ui-message-stream', 'v1')
        ->assertHeader('x-conversation-title', 'Hello');
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));

    \expect($response->streamedContent())
        ->toContain('"type":"text-delta"')
        ->toContain('"delta":"Hello"')
        ->toContain('"delta":" from"')
        ->toContain('"delta":" Stockflow"')
        ->and($admin->conversations()->whereKey($conversationId)->exists())->toBeTrue()
        ->and(ConversationMessage::query()->where('conversation_id', $conversationId)->where('role', 'user')->exists())->toBeTrue();
});

\test('assistant emits and persists an individual native mutation approval', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    StockflowAssistant::fake([
        new ToolCall('movement-approval', 'write_inventory_counts', [
            'request' => [
                'action' => 'start_inventory_draft',
                'store_id' => $warehouse->getKey(),
            ],
        ]),
    ]);

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Receive two units',
    ]);
    $conversationId = Typer::assertString($response->headers->get('x-conversation-id'));

    \expect($response->streamedContent())
        ->toContain('"type":"tool-approval-request"')
        ->toContain('"toolCallId":"movement-approval"')
        ->and(ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereNotNull('approval_state')
            ->exists())->toBeTrue();
});

\test('main admin cannot continue another participants conversation', function (): void {
    StockflowAssistant::fake(['Should not run']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $otherAdmin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = $otherAdmin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Private',
    ]);

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Hello',
        'conversation_id' => $conversation->getKey(),
    ])->assertNotFound();

    StockflowAssistant::assertNeverPrompted();
});

\test('assistant message and decisions are mutually exclusive', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'Hello',
        'conversation_id' => Str::uuid()->toString(),
        'decisions' => ['call-1' => ['action' => 'approve']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['message', 'decisions']);

    StockflowAssistant::assertNeverPrompted();
});

\test('assistant approval continuation requires a conversation and nonempty decisions', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'decisions' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['conversation_id', 'decisions']);

    StockflowAssistant::assertNeverPrompted();
});

\test('assistant business approvals reject browser-supplied argument edits', function (): void {
    StockflowAssistant::fake(['Should not run']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = $admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Locked approval',
    ]);
    $arguments = [
        'request' => [
            'action' => 'create_stock_movement',
            'mode' => 'incoming',
            'store_id' => 10,
            'values' => [
                'items' => [[
                    'item_id' => 20,
                    'quantity' => 2,
                ]],
            ],
        ],
    ];
    $conversation->messages()->create([
        'id' => Str::uuid()->toString(),
        'participant_type' => $admin->getMorphClass(),
        'participant_id' => $admin->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [['id' => 'call-locked', 'name' => 'write_stock_movements', 'arguments' => $arguments]],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'approval_state' => ['pending' => ['call-locked' => 'Approval required']],
    ]);

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'call-locked' => ['action' => 'edit', 'arguments' => $arguments],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['decisions.call-locked.arguments']);

    StockflowAssistant::assertNeverPrompted();
});

\test('assistant rejects arguments disguised as an ordinary approval', function (): void {
    StockflowAssistant::fake(['Should not run']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $arguments = [
        'request' => [
            'action' => 'create_financial_row',
            'store_id' => 10,
            'context' => ['year' => 2026, 'month' => 8],
            'values' => ['direction' => 'expense', 'label' => 'Original', 'occurred_on' => '2026-08-28', 'amount' => 10, 'note' => null],
        ],
    ];
    $conversation = \createPendingAssistantConversation($admin, 'approve-with-arguments', $arguments);
    $arguments['request']['store_id'] = 999;

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'approve-with-arguments' => ['action' => 'approve', 'arguments' => $arguments],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['decisions.approve-with-arguments.arguments']);

    StockflowAssistant::assertNeverPrompted();
});

\test('assistant rejects the removed public edit decision', function (): void {
    StockflowAssistant::fake(['Should not run']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = \createPendingAssistantConversation($admin, 'edit-without-arguments', [
        'request' => ['action' => 'start_inventory_draft', 'store_id' => 10],
    ], 'write_inventory_counts');

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'edit-without-arguments' => ['action' => 'edit'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['decisions.edit-without-arguments.action']);

    StockflowAssistant::assertNeverPrompted();
});

\test('assistant converts each rejected tool call into its own native reject decision', function (): void {
    StockflowAssistant::fake(['Continued after rejection']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = \createPendingAssistantConversation($admin, 'rejected-call', [
        'request' => ['action' => 'start_inventory_draft', 'store_id' => 10],
    ], 'write_inventory_counts');

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'rejected-call' => ['action' => 'reject'],
        ],
    ]);

    $response->assertOk();
    $response->streamedContent();

    StockflowAssistant::assertPrompted(function (AgentPrompt $prompt): bool {
        $decision = $prompt->approvalDecisions?->get('rejected-call');

        return $decision?->isRejected() === true && $decision->result === null;
    });
});

\test('assistant converts a locked clarification option into a native edit decision', function (): void {
    StockflowAssistant::fake(['Continued after selection']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $arguments = [
        'question' => 'Which store should be used?',
        'options' => [
            ['id' => 'brno', 'label' => 'Brno'],
            ['id' => 'ostrava', 'label' => 'Ostrava'],
        ],
    ];
    $conversation = \createPendingAssistantConversation(
        $admin,
        'choice-call',
        $arguments,
        'ask_user_choice',
    );

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'choice-call' => ['action' => 'select', 'option_id' => 'ostrava'],
        ],
    ]);

    $response->assertOk();
    $response->streamedContent();

    StockflowAssistant::assertPrompted(function (AgentPrompt $prompt) use ($arguments): bool {
        $decision = $prompt->approvalDecisions?->get('choice-call');

        return $decision?->isEdited() === true && $decision->arguments === [
            ...$arguments,
            'selected_option_id' => 'ostrava',
        ];
    });
});

\test('assistant rejects a clarification option not declared by the pending tool call', function (): void {
    StockflowAssistant::fake(['Should not run']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = \createPendingAssistantConversation($admin, 'choice-call', [
        'question' => 'Which store should be used?',
        'options' => [
            ['id' => 'brno', 'label' => 'Brno'],
            ['id' => 'ostrava', 'label' => 'Ostrava'],
        ],
    ], 'ask_user_choice');

    $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'choice-call' => ['action' => 'select', 'option_id' => 'prague'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['decisions.choice-call.option_id']);

    StockflowAssistant::assertNeverPrompted();
});

\test('assistant sends one explicit decision for each pending tool call', function (): void {
    StockflowAssistant::fake(['Continued after two decisions']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $firstArguments = [
        'request' => ['action' => 'start_inventory_draft', 'store_id' => 10],
    ];
    $conversation = \createPendingAssistantConversation($admin, 'first-call', $firstArguments, 'write_inventory_counts');
    $message = Typer::assertInstance($conversation->messages()->sole(), ConversationMessage::class);
    $message->setAttribute('tool_calls', [
        ['id' => 'first-call', 'name' => 'write_inventory_counts', 'arguments' => $firstArguments],
        ['id' => 'second-call', 'name' => 'write_inventory_counts', 'arguments' => $firstArguments],
    ]);
    $message->setAttribute('approval_state', [
        'pending' => ['first-call' => 'First', 'second-call' => 'Second'],
    ]);
    $message->save();

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'conversation_id' => $conversation->getKey(),
        'decisions' => [
            'first-call' => ['action' => 'approve'],
            'second-call' => ['action' => 'reject'],
        ],
    ]);

    $response->assertOk();
    $response->streamedContent();

    StockflowAssistant::assertPrompted(function (AgentPrompt $prompt): bool {
        return \count($prompt->approvalDecisions?->all() ?? []) === 2 &&
            $prompt->approvalDecisions->get('first-call')?->isApproved() === true &&
            $prompt->approvalDecisions->get('second-call')?->isRejected() === true;
    });
});

\test('provider generation failures surface without executing an application tool', function (): void {
    StockflowAssistant::fake(static function (): never {
        throw new RuntimeException('The AI provider is unavailable.');
    });
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->be($admin, 'users')->postJson('/assistant/chat', [
        'message' => 'What is in stock?',
    ]);

    $response->assertOk();
    $stream = Typer::assertInstance($response->baseResponse, StreamedResponse::class);
    $callback = $stream->getCallback();

    \expect($callback)->not->toBeNull()
        ->and(static fn(): mixed => $callback())
        ->toThrow(RuntimeException::class, 'The AI provider is unavailable.');
    StockflowAssistant::assertPromptedTimes(1);
});

\test('concurrent turns on one conversation are rejected by the execution lock', function (): void {
    StockflowAssistant::fake(['Should not run']);
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $conversation = $admin->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Busy conversation',
    ]);
    $lock = Resolver::resolveCacheManager()
        ->store(Config::inject()->assertString('ai.assistant.lock_store'))
        ->lock('assistant:conversation:' . $conversation->getKey(), 130);
    $lock->get();

    try {
        $this->be($admin, 'users')->postJson('/assistant/chat', [
            'conversation_id' => $conversation->getKey(),
            'message' => 'Run concurrently',
        ])->assertConflict();
    } finally {
        $lock->release();
    }

    StockflowAssistant::assertNeverPrompted();
});

/**
 * Create an official assistant conversation paused on a generic mutation approval.
 *
 * @param array<string, mixed> $arguments
 */
function createPendingAssistantConversation(User $user, string $toolCallId, array $arguments, string $toolName = 'write_financial_reports'): Laravel\Ai\Models\Conversation
{
    $conversation = $user->conversations()->create([
        'id' => Str::uuid()->toString(),
        'title' => 'Pending assistant operation',
    ]);
    $conversation->messages()->create([
        'id' => Str::uuid()->toString(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'agent' => StockflowAssistant::class,
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'tool_calls' => [[
            'id' => $toolCallId,
            'name' => $toolName,
            'arguments' => $arguments,
        ]],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'approval_state' => ['pending' => [$toolCallId => 'Approval required']],
    ]);

    return $conversation;
}
