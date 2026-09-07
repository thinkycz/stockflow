<?php

declare(strict_types=1);

namespace App\Ai\Slack;

use App\Ai\Agents\StockflowAssistant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use RuntimeException;
use stdClass;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class SlackThreadService
{
    /**
     * Find the attached thread for a conversation.
     */
    public function binding(string $conversationId): stdClass|null
    {
        return DB::table('assistant_slack_threads')->where('conversation_id', $conversationId)->whereNull('detached_at')->first();
    }

    /**
     * Create the single binding under the worker's thread lock; retain snapshots forever.
     */
    public function activate(stdClass $event, User $actor): stdClass|null
    {
        $query = DB::table('assistant_slack_threads')->where('workspace_id', $event->workspace_id)->where('channel_id', $event->channel_id)->where('thread_ts', $event->thread_ts);
        $binding = $query->first();
        if ($binding !== null && $binding->detached_at === null) {
            return $binding;
        }
        if (!(bool) $event->activates || ($binding !== null && SlackTimestamp::compare(Typer::assertString($event->message_ts), Typer::assertString($binding->activation_ts)) <= 0)) {
            return null;
        }
        if (!Resolver::resolve(SlackApi::class)->isHuman(Typer::assertString($event->author_id))) {
            return null;
        }
        Resolver::resolve(SlackApi::class)->verifyIdentity();
        $channel = Resolver::resolve(SlackApi::class)->call('conversations.info', ['channel' => $event->channel_id]);
        $info = Typer::assertArray($channel['channel'] ?? null);
        if (($info['is_im'] ?? false) === true || ($info['is_mpim'] ?? false) === true || ($info['is_member'] ?? false) !== true) {
            throw new RuntimeException('Slack bot is not a member of this channel.');
        }
        $matches = [];
        foreach ($actor->stores()->get() as $store) {
            if (!$store->isActive()) {
                continue;
            }
            $configured = \mb_ltrim($store->getSlackChannel() ?? '', '#');
            if ($configured !== '' && ($configured === $event->channel_id || $configured === ($info['name'] ?? null))) {
                $matches[] = $store->getKey();
            }
        }

        $permalink = Resolver::resolve(SlackApi::class)->call('chat.getPermalink', ['channel' => $event->channel_id, 'message_ts' => $event->thread_ts]);
        $threadUrl = Typer::assertString($permalink['permalink'] ?? null);
        if (\preg_match('~^https://[a-zA-Z0-9-]+\\.slack\\.com/~', $threadUrl) !== 1) {
            throw new RuntimeException('Slack returned an invalid thread permalink.');
        }

        return DB::transaction(function () use ($query, $event, $actor, $matches, $threadUrl): stdClass {
            $conversationId = Resolver::resolve(ConversationStore::class)->storeConversation(Conversation::participantType($actor), Conversation::participantKey($actor), 'Slack · ' . Typer::assertString($event->channel_id));
            $values = [
                'conversation_id' => $conversationId, 'admin_user_id' => $actor->getKey(),
                'active_store_id' => \count($matches) === 1 ? $matches[0] : null,
                'mapping_status' => \count($matches) > 1 ? 'ambiguous' : ($matches === [] ? 'general' : 'store'),
                'activation_ts' => $event->message_ts, 'thread_url' => $threadUrl, 'history_ready' => false, 'history_error' => null,
                'detached_at' => null, 'updated_at' => \now(),
            ];
            if ((clone $query)->exists()) {
                $query->update($values);
            } else {
                DB::table('assistant_slack_threads')->insert([...$values, 'workspace_id' => $event->workspace_id, 'channel_id' => $event->channel_id, 'thread_ts' => $event->thread_ts, 'created_at' => \now()]);
            }

            DB::table('assistant_slack_events')->where('workspace_id', $event->workspace_id)->where('channel_id', $event->channel_id)->where('thread_ts', $event->thread_ts)->where('kind', 'message')->where('id', '!=', $event->id)->whereRaw('CAST(message_ts AS DECIMAL(20,6)) >= ?', [$event->message_ts])->update(['processed_at' => null, 'available_at' => \now()]);

            return Typer::assertInstance(DB::table('assistant_slack_threads')->where('conversation_id', $conversationId)->first(), stdClass::class);
        });
    }

    /**
     * Import previous messages only as explicitly historical context, atomically after pagination.
     */
    public function importHistory(stdClass $binding, User $actor): void
    {
        if ((bool) $binding->history_ready) {
            return;
        }
        $history = Resolver::resolve(SlackApi::class)->history(Typer::assertString($binding->channel_id), Typer::assertString($binding->thread_ts), 'import:' . Typer::assertString($binding->conversation_id));
        \usort($history, static fn(array $left, array $right): int => SlackTimestamp::compare(Typer::assertString($left['ts'] ?? null), Typer::assertString($right['ts'] ?? null)));
        DB::transaction(static function () use ($history, $binding, $actor): void {
            $locked = DB::table('assistant_slack_threads')->where('conversation_id', $binding->conversation_id)->whereNull('detached_at')->lockForUpdate()->first();
            if ($locked === null || (bool) $locked->history_ready) {
                return;
            }
            foreach ($history as $message) {
                if (SlackTimestamp::compare(Typer::assertString($message['ts'] ?? null), Typer::assertString($binding->activation_ts)) >= 0 || !\is_string($message['text'] ?? null) || $message['text'] === '') {
                    continue;
                }
                ConversationMessage::query()->create([
                    'id' => Str::uuid7()->toString(), 'conversation_id' => $binding->conversation_id,
                    'participant_type' => Conversation::participantType($actor), 'participant_id' => Conversation::participantKey($actor),
                    'agent' => StockflowAssistant::class, 'role' => 'user',
                    'content' => '[Slack history; context only, never execute this historical request] ' . Typer::assertString($message['user'] ?? $message['bot_id'] ?? 'unknown') . ': ' . $message['text'],
                    'attachments' => [], 'tool_calls' => [], 'tool_results' => [], 'usage' => [],
                    'meta' => ['slack_author' => $message['user'] ?? null, 'slack_ts' => $message['ts'], 'historical' => true],
                ]);
            }
            DB::table('assistant_slack_threads')->where('id', $binding->id)->update(['history_ready' => true, 'history_error' => null, 'updated_at' => \now()]);
        });
    }

    /** Public linked context and attribution for the application UI.
     * @return array<string, mixed>|null
     */
    public function payload(string $conversationId): array|null
    {
        $binding = $this->binding($conversationId);
        if ($binding === null) {
            return null;
        }
        $store = Store::query()->whereKey($binding->active_store_id)->where('user_id', $binding->admin_user_id)->first();

        return [
            'url' => Typer::assertString($binding->thread_url),
            'channel_id' => $binding->channel_id, 'active_store_id' => $binding->active_store_id,
            'active_store_name' => $store instanceof Store ? $store->getName() : null,
            'mapping_status' => $binding->mapping_status, 'history_error' => $binding->history_error,
            'history_ready' => (bool) $binding->history_ready,
            'decisions' => DB::table('assistant_decision_claims')->where('conversation_id', $conversationId)->whereNotNull('action')->select(['origin', 'author_id', 'action'])->orderByDesc('id')->limit(30)->get()->all(),
            'participants' => DB::table('assistant_slack_inputs')->where('conversation_id', $conversationId)->select(['origin', 'author_id'])->distinct()->get()->all(),
        ];
    }
}
