<?php

declare(strict_types=1);

use App\Jobs\ProcessSlackEventJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

\test('the owning admin can requeue failed history from the application', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Config::inject()->assign('ai.assistant.enabled', true);
    Queue::fake();
    $id = Resolver::resolve(ConversationStore::class)->storeConversation(Conversation::participantType($admin), $admin->getKey(), 'Slack');
    DB::table('assistant_slack_threads')->insert(['workspace_id' => 'T1', 'channel_id' => 'C1', 'thread_ts' => '100.000001', 'conversation_id' => $id, 'admin_user_id' => $admin->getKey(), 'activation_ts' => '100.000001']);
    DB::table('assistant_slack_events')->insert(['delivery_key' => 'key', 'workspace_id' => 'T1', 'channel_id' => 'C1', 'thread_ts' => '100.000001', 'message_ts' => '100.000001', 'author_id' => 'U1', 'kind' => 'message', 'payload' => '', 'available_at' => \now()->addHour()]);
    $this->be($admin, 'users')->withSession(['_token' => 'csrf'])->withHeader('X-CSRF-TOKEN', 'csrf')->post('/assistant/conversations/' . $id . '/slack-history-retry')->assertRedirect('/assistant/conversations/' . $id);
    Queue::assertPushed(ProcessSlackEventJob::class);
});
