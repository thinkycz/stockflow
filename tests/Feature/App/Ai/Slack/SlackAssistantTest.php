<?php

declare(strict_types=1);

use App\Ai\Agents\StockflowAssistant;
use App\Ai\Agents\StockflowConversationTitleAgent;
use App\Ai\AssistantConversationLock;
use App\Ai\AssistantTurnService;
use App\Ai\ConversationRepository;
use App\Ai\Slack\SlackIngress;
use App\Ai\Slack\SlackOutbox;
use App\Ai\Slack\SlackTurnAdmission;
use App\Domain\Stores\StoreManagementService;
use App\Enums\AssistantTurnStatusEnum;
use App\Jobs\DeliverSlackMessageJob;
use App\Jobs\MaintainAssistantTurnsJob;
use App\Jobs\MaintainSlackAssistantJob;
use App\Jobs\ProcessSlackEventJob;
use App\Jobs\RunAssistantTurnJob;
use App\Models\AssistantTurn;
use App\Models\Store;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\AiManager;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

\beforeEach(function (): void {
    [$this->admin] = \createIsolatedUserWithWarehouse();
    foreach (['ai.assistant.enabled' => true, 'services.slack.assistant.enabled' => true, 'services.slack.assistant.admin_user_id' => $this->admin->getKey(), 'services.slack.assistant.workspace_id' => 'TTEAM', 'services.slack.assistant.signing_secret' => 'test-secret', 'services.slack.assistant.bot_user_id' => 'UBOT', 'services.slack.notifications.bot_user_oauth_token' => 'test-token'] as $key => $value) {
        Config::inject()->assign($key, $value);
    }
    Queue::fake();
    \slackHttp();
    StockflowAssistant::fake(['Answer']);
    StockflowConversationTitleAgent::fake(['Slack question']);
});

/**
 * Replace complete HTTP stubs, including defaults.
 */
function slackHttp(array $overrides = []): void
{
    Http::swap(new Factory());
    Http::preventStrayRequests();
    Http::fake([...[
        'slack.com/api/chat.getPermalink' => Http::response(['ok' => true, 'permalink' => 'https://teacha.slack.com/archives/CSTORE/p100000001']),
        'slack.com/api/auth.test' => Http::response(['ok' => true, 'team_id' => 'TTEAM', 'user_id' => 'UBOT']),
        'slack.com/api/users.info' => Http::response(['ok' => true, 'user' => ['is_bot' => false]]),
        'slack.com/api/conversations.info' => Http::response(['ok' => true, 'channel' => ['id' => 'CSTORE', 'name' => 'zizkov', 'is_member' => true]]),
        'slack.com/api/conversations.replies' => Http::response(['ok' => true, 'messages' => [], 'response_metadata' => ['next_cursor' => '']]),
        'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '110.000001']),
    ], ...$overrides]);
}

/**
 * Persist and process one human message.
 */
function slackInput(string $text = '<@UBOT> hello', string $ts = '100.000001', string $channel = 'CSTORE', string $author = 'UALICE'): int
{
    Resolver::resolve(SlackIngress::class)->accept(['type' => 'event_callback', 'event' => ['type' => 'message', 'channel' => $channel, 'user' => $author, 'ts' => $ts, 'thread_ts' => '100.000001', 'text' => $text]]);
    $id = DB::table('assistant_slack_events')->where('message_ts', $ts)->where('channel_id', $channel)->value('id');
    (new ProcessSlackEventJob($id))->handle();

    return $id;
}

/**
 * Resolve the only linked application conversation.
 */
function slackConversation(): Conversation
{
    return Conversation::query()->whereKey(DB::table('assistant_slack_threads')->value('conversation_id'))->firstOrFail();
}

/**
 * Seed an SDK-native pending choice or approval.
 */
function slackPending(Conversation $conversation, string $name = 'ask_user_choice'): void
{
    ConversationMessage::query()->create([
        'id' => Str::uuid7()->toString(), 'conversation_id' => $conversation->getKey(), 'agent' => StockflowAssistant::class,
        'role' => 'assistant', 'content' => '', 'attachments' => [], 'tool_calls' => [['id' => 'call-one', 'name' => $name, 'arguments' => ['question' => 'Where?', 'options' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']]]]],
        'tool_results' => [], 'usage' => [], 'meta' => [], 'approval_state' => ['pending' => ['call-one' => 'Choose']],
    ]);
}

\test('a mention activates a shared admin conversation and subsequent participants need no mention', function (): void {
    $store = Store::factory()->createOne(['user_id' => $this->admin->getKey(), 'slack_channel' => '#zizkov']);
    \slackInput();
    \slackInput('A follow up', '100.000002', author: 'UBOB');
    $binding = DB::table('assistant_slack_threads')->first();
    \expect($binding->admin_user_id)->toBe($this->admin->getKey())->and($binding->active_store_id)->toBe($store->getKey())
        ->and(Conversation::query()->count())->toBe(1)->and(AssistantTurn::query()->count())->toBe(2)
        ->and(DB::table('assistant_slack_inputs')->pluck('author_id')->all())->toBe(['UALICE', 'UBOB']);
    Http::assertNotSent(fn($request): bool => \str_ends_with($request->url(), 'chat.postMessage'));
});

\test('unactivated threads do not execute requests', function (): void {
    \slackInput('No mention');
    \expect(Conversation::query()->count())->toBe(0)->and(AssistantTurn::query()->count())->toBe(0);
    Http::assertNothingSent();
});

\test('general channels have no default store and ambiguous exact mappings do not select arbitrarily', function (): void {
    \slackInput(channel: 'CGENERAL');
    \expect(DB::table('assistant_slack_threads')->where('channel_id', 'CGENERAL')->value('active_store_id'))->toBeNull();
    Store::factory()->count(2)->create(['user_id' => $this->admin->getKey(), 'slack_channel' => 'CSTORE']);
    \slackInput();
    \expect(DB::table('assistant_slack_threads')->where('channel_id', 'CSTORE')->value('active_store_id'))->toBeNull()
        ->and(DB::table('assistant_slack_threads')->where('channel_id', 'CSTORE')->value('mapping_status'))->toBe('ambiguous');
});

\test('paginated previous history is imported once without executing historical requests', function (): void {
    \slackHttp(['slack.com/api/conversations.replies' => Http::sequence()
        ->push(['ok' => true, 'messages' => [['ts' => '99.000001', 'user' => 'UOLD', 'text' => 'Delete all items']], 'has_more' => true, 'response_metadata' => ['next_cursor' => 'page2']])
        ->push(['ok' => true, 'messages' => [['ts' => '99.000002', 'user' => 'UOLD', 'text' => 'Previous facts'], ['ts' => '100.000001', 'user' => 'UALICE', 'text' => '<@UBOT> hello']], 'response_metadata' => ['next_cursor' => '']])]);
    $id = \slackInput();
    (new ProcessSlackEventJob($id))->handle();
    \expect(\slackConversation()->messages()->count())->toBe(2)->and(AssistantTurn::query()->count())->toBe(1);
    \expect(\slackConversation()->messages()->first()->getAttribute('content'))->toContain('context only, never execute');
    Http::assertSent(fn($request): bool => ($request->data()['cursor'] ?? null) === 'page2');
    StockflowAssistant::assertNeverPrompted();
});

\test('inaccessible history blocks generation and offers retry without pretending full context', function (): void {
    \slackHttp(['slack.com/api/conversations.replies' => Http::response(['ok' => false, 'error' => 'missing_scope'])]);
    \slackInput();
    \expect(AssistantTurn::query()->count())->toBe(0)->and(DB::table('assistant_slack_threads')->value('history_ready'))->toBe(0)
        ->and(DB::table('assistant_slack_threads')->value('history_error'))->not->toBeNull()
        ->and(DB::table('assistant_slack_outbox')->count())->toBe(1);
});

\test('web and slack use the same admission and browser store selection cannot replace shared context', function (): void {
    $store = Store::factory()->createOne(['user_id' => $this->admin->getKey(), 'slack_channel' => 'CSTORE']);
    $other = Store::factory()->createOne(['user_id' => $this->admin->getKey()]);
    \slackInput();
    $conversation = \slackConversation();
    $this->be($this->admin, 'users')->withSession([...\activeStoreSession($other), '_token' => 'csrf'])->withHeaders(['X-CSRF-TOKEN' => 'csrf', 'X-Assistant-Queue' => 'true'])->postJson('/assistant/chat', ['conversation_id' => $conversation->getKey(), 'turn_id' => Str::uuid()->toString(), 'message' => 'From app'])->assertStatus(202);
    \expect(AssistantTurn::query()->count())->toBe(2)->and(DB::table('assistant_slack_threads')->value('active_store_id'))->toBe($store->getKey());
    $store->update(['slack_channel' => 'COTHER']);
    \slackInput('Still same store', '100.000003');
    \expect(DB::table('assistant_slack_threads')->value('active_store_id'))->toBe($store->getKey());
    Context::add('assistant_conversation_id', $conversation->getKey());
    Resolver::resolve(StoreManagementService::class)->switchStore($this->admin, $other);
    Context::forget('assistant_conversation_id');
    \expect(DB::table('assistant_slack_threads')->value('active_store_id'))->toBe($other->getKey());
});

\test('pending messages stay queued and first valid decision wins across both interfaces', function (): void {
    \slackInput();
    $conversation = \slackConversation();
    AssistantTurn::query()->update(['status' => 'completed']);
    \slackPending($conversation);
    \slackInput('Wait for this', '100.000002');
    $admission = Resolver::resolve(SlackTurnAdmission::class);
    \expect($admission->next($conversation))->toBeNull();
    $decisionId = Str::uuid()->toString();
    $payload = ['decisions' => ['call-one' => ['action' => 'select', 'option_id' => 'a']]];
    $admission->submit($this->admin, $conversation, $decisionId, 'decisions', $payload, 'slack', 'UBOB');
    \expect($admission->next($conversation)->getTurnId())->toBe($decisionId);
    $admission->submit($this->admin, $conversation, $decisionId, 'decisions', $payload, 'slack', 'UBOB');
    try {
        $admission->submit($this->admin, $conversation, Str::uuid()->toString(), 'decisions', $payload, 'web', (string) $this->admin->getKey());
        $this->fail('A second decision must lose.');
    } catch (HttpException $exception) {
        \expect($exception->getStatusCode())->toBe(409);
    }
    \expect(DB::table('assistant_decision_claims')->value('author_id'))->toBe('UBOB')->and(DB::table('assistant_decision_claims')->count())->toBe(1);
});

\test('a busy conversation retains incoming work and duplicate runner jobs do not generate twice', function (): void {
    \slackInput();
    $conversation = \slackConversation();
    $turn = AssistantTurn::query()->firstOrFail();
    $lock = Resolver::resolve(AssistantConversationLock::class)->acquire((string) $conversation->getKey());
    Resolver::resolveApp()->call([new RunAssistantTurnJob($turn->getTurnId()), 'handle']);
    \expect($turn->fresh()->getStatus())->toBe(AssistantTurnStatusEnum::QUEUED);
    $lock->release();
    Resolver::resolveApp()->call([new RunAssistantTurnJob($turn->getTurnId()), 'handle']);
    Resolver::resolveApp()->call([new RunAssistantTurnJob($turn->getTurnId()), 'handle']);
    StockflowAssistant::assertPromptedTimes(1);
    \expect($turn->fresh()->getStatus())->toBe(AssistantTurnStatusEnum::COMPLETED);
});

\test('deletion detaches the thread and a fresh mention can activate a new conversation', function (): void {
    \slackInput();
    $old = \slackConversation()->getKey();
    Resolver::resolve(ConversationRepository::class)->delete(\slackConversation());
    \slackInput('No longer active', (string) (\time() + 1) . '.000001');
    \expect(Conversation::query()->count())->toBe(0);
    \slackInput('<@UBOT> Start again', (string) (\time() + 2) . '.000001');
    \expect(\slackConversation()->getKey())->not->toBe($old);
});

\test('outbox splits long text retries rate limits and never invokes the assistant', function (): void {
    \slackInput();
    DB::table('assistant_slack_outbox')->delete();
    Resolver::resolve(SlackOutbox::class)->enqueue((string) \slackConversation()->getKey(), 'long', \str_repeat('x', 6000));
    $ids = DB::table('assistant_slack_outbox')->pluck('id');
    \expect($ids)->toHaveCount(3);
    \slackHttp(['slack.com/api/chat.postMessage' => Http::sequence()->push([], 429, ['Retry-After' => '90'])->push(['ok' => true, 'ts' => '110.000001'])]);
    (new DeliverSlackMessageJob($ids[0]))->handle();
    \expect(DB::table('assistant_slack_outbox')->where('id', $ids[0])->value('sent_at'))->toBeNull();
    $this->travel(91)->seconds();
    (new DeliverSlackMessageJob($ids[0]))->handle();
    (new DeliverSlackMessageJob($ids[0]))->handle();
    \expect(DB::table('assistant_slack_outbox')->where('id', $ids[0])->value('sent_at'))->not->toBeNull();
    StockflowAssistant::assertNeverPrompted();
});

\test('maintenance preserves queued linked messages during long approval waits', function (): void {
    \slackInput();
    AssistantTurn::query()->update(['updated_at' => \now()->subHour()]);
    (new MaintainAssistantTurnsJob())->handle();
    \expect(AssistantTurn::query()->firstOrFail()->getStatus())->toBe(AssistantTurnStatusEnum::QUEUED);
    (new MaintainSlackAssistantJob())->handle();
    Queue::assertPushed(RunAssistantTurnJob::class);
});

\test('a real approved inventory mutation executes once across Slack and web decisions', function (): void {
    $store = Store::factory()->createOne(['user_id' => $this->admin->getKey(), 'slack_channel' => 'CSTORE']);
    Ai::swap(new AiManager(Resolver::resolveApp()));
    StockflowConversationTitleAgent::fake(['Inventory']);
    Ai::textProvider('openrouter')->useTextGateway(new FakeTextGateway([
        new ToolCall('inventory-approval', 'write_inventory_counts', ['request' => ['action' => 'start_inventory_draft', 'store_id' => $store->getKey()]]),
        'Inventory created',
    ]));
    \slackInput();
    $conversation = \slackConversation();
    $first = AssistantTurn::query()->firstOrFail();
    Resolver::resolveApp()->call([new RunAssistantTurnJob($first->getTurnId()), 'handle']);
    \expect($first->fresh()->getStatus())->toBe(AssistantTurnStatusEnum::AWAITING_APPROVAL);
    $id = Str::uuid()->toString();
    $admission = Resolver::resolve(SlackTurnAdmission::class);
    $payload = ['decisions' => ['inventory-approval' => ['action' => 'approve']]];
    $admission->submit($this->admin, $conversation, $id, 'decisions', $payload, 'slack', 'UBOB');
    Resolver::resolveApp()->call([new RunAssistantTurnJob($id), 'handle']);
    Resolver::resolveApp()->call([new RunAssistantTurnJob($id), 'handle']);
    \expect(AssistantTurn::query()->whereKey($id)->value('error_summary'))->toBeNull();
    \expect(DB::table('inventory_sessions')->count())->toBe(1);
    try {
        $admission->submit($this->admin, $conversation, Str::uuid()->toString(), 'decisions', $payload, 'web', (string) $this->admin->getKey());
        $this->fail('A stale decision must be refused.');
    } catch (HttpException $exception) {
        \expect($exception->getStatusCode())->toBe(409);
    }
    \expect(DB::table('inventory_sessions')->count())->toBe(1);
});

\test('history resumes from its saved cursor after a rate limit and worker restart', function (): void {
    \slackHttp(['slack.com/api/conversations.replies' => Http::sequence()
        ->push(['ok' => true, 'messages' => [['ts' => '99.000001', 'user' => 'UOLD', 'text' => 'Old']], 'response_metadata' => ['next_cursor' => 'page2']])
        ->push([], 429, ['Retry-After' => '90'])
        ->push(['ok' => true, 'messages' => [['ts' => '99.000002', 'user' => 'UOLD', 'text' => 'Old 2']], 'response_metadata' => ['next_cursor' => '']])]);
    $eventId = \slackInput();
    \expect(AssistantTurn::query()->count())->toBe(0)->and(DB::table('assistant_slack_history_pages')->count())->toBe(1);
    $this->travel(91)->seconds();
    (new ProcessSlackEventJob($eventId))->handle();
    \expect(AssistantTurn::query()->count())->toBe(1)->and(\slackConversation()->messages()->count())->toBe(2);
    $requests = Http::recorded(fn($request): bool => \str_ends_with($request->url(), 'conversations.replies'));
    \expect($requests)->toHaveCount(3)->and($requests->values()[2][0]['cursor'])->toBe('page2');
});

\test('cancelled queued work never prompts and does not block the next input', function (): void {
    \slackInput();
    $turn = AssistantTurn::query()->firstOrFail();
    \slackInput('Next', '100.000002');
    Resolver::resolve(AssistantTurnService::class)->requestCancellation($turn);
    Resolver::resolveApp()->call([new RunAssistantTurnJob($turn->getTurnId()), 'handle']);
    \expect($turn->fresh()->getStatus())->toBe(AssistantTurnStatusEnum::CANCELLED)
        ->and(Resolver::resolve(SlackTurnAdmission::class)->next(\slackConversation()))->not->toBeNull();
    StockflowAssistant::assertNeverPrompted();
});

\test('an uncertain outbound delivery is reconciled by metadata without reposting', function (): void {
    \slackInput();
    $row = DB::table('assistant_slack_outbox')->first();
    DB::table('assistant_slack_outbox')->where('id', $row->id)->update(['attempts' => 1]);
    \slackHttp(['slack.com/api/conversations.replies' => Http::response(['ok' => true, 'messages' => [['ts' => '110.000001', 'metadata' => ['event_type' => 'stockflow_delivery', 'event_payload' => ['delivery_key' => $row->delivery_key]]]], 'response_metadata' => ['next_cursor' => '']])]);
    (new DeliverSlackMessageJob($row->id))->handle();
    \expect(DB::table('assistant_slack_outbox')->where('id', $row->id)->value('message_ts'))->toBe('110.000001');
    Http::assertNotSent(fn($request): bool => \str_ends_with($request->url(), 'chat.postMessage'));
});

\test('multiple participants decisions form one complete native approval batch', function (): void {
    \slackInput();
    $conversation = \slackConversation();
    Resolver::resolveApp()->call([new RunAssistantTurnJob(AssistantTurn::query()->firstOrFail()->getTurnId()), 'handle']);
    $a = Store::factory()->createOne(['user_id' => $this->admin->getKey()]);
    $b = Store::factory()->createOne(['user_id' => $this->admin->getKey()]);
    $calls = [];
    foreach (['call-a' => $a, 'call-b' => $b] as $call => $store) {
        $calls[] = ['id' => $call, 'name' => 'write_inventory_counts', 'arguments' => ['request' => ['action' => 'start_inventory_draft', 'store_id' => $store->getKey()]]];
    }
    ConversationMessage::query()->create(['id' => Str::uuid7()->toString(), 'conversation_id' => $conversation->getKey(), 'agent' => StockflowAssistant::class, 'role' => 'assistant', 'content' => '', 'attachments' => [], 'tool_calls' => $calls, 'tool_results' => [], 'usage' => [], 'meta' => [], 'approval_state' => ['pending' => ['call-a' => 'Start A', 'call-b' => 'Start B']]]);
    $admission = Resolver::resolve(SlackTurnAdmission::class);
    $first = Str::uuid()->toString();
    $second = Str::uuid()->toString();
    $admission->submit($this->admin, $conversation, $first, 'decisions', ['decisions' => ['call-a' => ['action' => 'approve']]], 'slack', 'UALICE');
    \expect($admission->next($conversation))->toBeNull();
    $admission->submit($this->admin, $conversation, $second, 'decisions', ['decisions' => ['call-b' => ['action' => 'approve']]], 'web', (string) $this->admin->getKey());
    \expect($admission->next($conversation)->getTurnId())->toBe($first);
    Ai::swap(new AiManager(Resolver::resolveApp()));
    Ai::textProvider('openrouter')->useTextGateway(new FakeTextGateway(['Both done']));
    Resolver::resolveApp()->call([new RunAssistantTurnJob($first), 'handle']);
    Resolver::resolveApp()->call([new RunAssistantTurnJob($second), 'handle']);
    \expect(DB::table('inventory_sessions')->count())->toBe(2)
        ->and(AssistantTurn::query()->whereKey($second)->firstOrFail()->getStatus())->toBe(AssistantTurnStatusEnum::COMPLETED);
});
