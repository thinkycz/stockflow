<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Slack\SlackApi;
use App\Ai\Slack\SlackConfiguration;
use App\Ai\Slack\SlackRateLimitException;
use App\Ai\Slack\SlackThreadService;
use App\Ai\Slack\SlackTransportLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class DeliverSlackMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Durable outbox owns retry timing.
     */
    public int $tries = 1;

    /**
     * Deliver one bounded Slack post independently of assistant execution.
     */
    public function __construct(/**
     * Durable outbox ID.
     */ public readonly int $outboxId)
    {
        $this->onConnection('assistant');
        $this->onQueue('assistant');
    }

    /**
     * Claim one channel thread and send its oldest pending post.
     */
    public function handle(): void
    {
        $admin = Resolver::resolve(SlackConfiguration::class)->admin();
        if ($admin === null) {
            return;
        }
        $row = DB::table('assistant_slack_outbox')->where('id', $this->outboxId)->whereNull('sent_at')->where('available_at', '<=', \now())->first();
        if ($row === null) {
            return;
        }
        $binding = Resolver::resolve(SlackThreadService::class)->binding(Typer::assertString($row->conversation_id));
        if ($binding === null || $binding->admin_user_id !== $admin->getKey()) {
            return;
        }
        $lock = Resolver::resolve(SlackTransportLock::class)->lock('slack:outbox:' . Typer::assertString($row->conversation_id), 60);
        if ($lock->get() !== true) {
            return;
        }
        try {
            $row = DB::table('assistant_slack_outbox')->where('id', $this->outboxId)->whereNull('sent_at')->first();
            if ($row === null) {
                return;
            }
            if (DB::table('assistant_slack_outbox')->where('conversation_id', $row->conversation_id)->whereNull('sent_at')->where('id', '<', $this->outboxId)->exists()) {
                return;
            }
            $payload = Typer::assertStringKeyArray(Typer::assertArray(\json_decode(Crypt::decryptString(Typer::assertString($row->payload)), true, flags: \JSON_THROW_ON_ERROR)));
            $api = Resolver::resolve(SlackApi::class);
            $api->verifyIdentity();
            if (Typer::assertInt($row->attempts) > 0) {
                foreach ($api->history(Typer::assertString($binding->channel_id), Typer::assertString($binding->thread_ts), 'delivery:' . $this->outboxId) as $message) {
                    $metadata = Typer::assertArray($message['metadata'] ?? []);
                    $eventPayload = Typer::assertArray($metadata['event_payload'] ?? []);
                    if (($eventPayload['delivery_key'] ?? null) === $row->delivery_key) {
                        DB::table('assistant_slack_outbox')->where('id', $this->outboxId)->update(['sent_at' => \now(), 'message_ts' => Typer::assertString($message['ts'] ?? null), 'error' => null, 'updated_at' => \now()]);

                        return;
                    }
                }
            }
            DB::table('assistant_slack_history_pages')->where('scan_id', 'delivery:' . $this->outboxId)->delete();
            DB::table('assistant_slack_outbox')->where('id', $this->outboxId)->increment('attempts');
            $result = Resolver::resolve(SlackApi::class)->call('chat.postMessage', [...$payload, 'channel' => $binding->channel_id, 'thread_ts' => $binding->thread_ts, 'metadata' => ['event_type' => 'stockflow_delivery', 'event_payload' => ['delivery_key' => $row->delivery_key]]]);
            DB::table('assistant_slack_outbox')->where('id', $this->outboxId)->update(['sent_at' => \now(), 'message_ts' => Typer::assertString($result['ts'] ?? null), 'error' => null, 'updated_at' => \now()]);
        } catch (Throwable $exception) {
            $delay = $exception instanceof SlackRateLimitException ? $exception->retryAfter : \min(3600, 30 * (2 ** \min(7, Typer::assertInt($row->attempts))));
            DB::table('assistant_slack_outbox')->where('id', $this->outboxId)->update(['attempts' => DB::raw('attempts + 1'), 'error' => \mb_substr($exception->getMessage(), 0, 1000), 'available_at' => \now()->addSeconds($delay), 'updated_at' => \now()]);
            \report($exception);
        } finally {
            $lock->release();
        }
    }
}
