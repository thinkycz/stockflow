<?php

declare(strict_types=1);

namespace App\Ai\Slack;

use App\Jobs\ProcessSlackEventJob;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

final class SlackIngress
{
    /** Persist one deduplicated delivery before HTTP acknowledgement.
     * @param array<array-key, mixed> $payload
     */
    public function accept(array $payload): void
    {
        $interactive = ($payload['type'] ?? null) === 'block_actions';
        $event = $interactive ? $payload : ($payload['event'] ?? null);
        if (!\is_array($event)) {
            return;
        }
        $channel = $interactive ? (Typer::assertArray($event['channel'] ?? [])['id'] ?? null) : ($event['channel'] ?? null);
        $author = $interactive ? (Typer::assertArray($event['user'] ?? [])['id'] ?? null) : ($event['user'] ?? null);
        $ts = $interactive ? (Typer::assertArray($event['container'] ?? [])['message_ts'] ?? null) : ($event['ts'] ?? null);
        $thread = $interactive ? (Typer::assertArray($event['message'] ?? [])['thread_ts'] ?? $ts) : ($event['thread_ts'] ?? $ts);
        if (!\is_string($channel) || \preg_match('/^[CG][A-Z0-9]+$/D', $channel) !== 1 || !\is_string($author) || !\is_string($ts) || !\is_string($thread)) {
            return;
        }
        $bot = Config::inject()->assertString('services.slack.assistant.bot_user_id');
        if ($author === $bot || (!$interactive && (isset($event['bot_id']) || !\in_array($event['subtype'] ?? null, [null, 'thread_broadcast', 'file_share'], true) || !\in_array($event['type'] ?? null, ['message', 'app_mention'], true)))) {
            return;
        }
        $workspace = Config::inject()->assertString('services.slack.assistant.workspace_id');
        $key = \hash('sha256', $workspace . ':' . $channel . ':' . ($interactive ? \json_encode($event['actions'] ?? [], \JSON_THROW_ON_ERROR) . ':' . $author . ':' . $ts : $ts));
        $activates = !$interactive && \str_contains(Typer::assertString($event['text'] ?? ''), '<@' . $bot . '>');
        DB::table('assistant_slack_events')->insertOrIgnore([
            'delivery_key' => $key, 'workspace_id' => $workspace, 'channel_id' => $channel,
            'thread_ts' => $thread, 'message_ts' => $ts, 'author_id' => $author,
            'kind' => $interactive ? 'interaction' : 'message', 'activates' => $activates,
            'payload' => Crypt::encryptString(\json_encode($event, \JSON_THROW_ON_ERROR)),
            'available_at' => \now(), 'created_at' => \now(), 'updated_at' => \now(),
        ]);
        $id = Typer::assertInt(DB::table('assistant_slack_events')->where('delivery_key', $key)->value('id'));
        \dispatch(new ProcessSlackEventJob($id))->afterCommit();
    }
}
