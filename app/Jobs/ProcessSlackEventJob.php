<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\AssistantTurnService;
use App\Ai\ConversationRepository;
use App\Ai\Slack\SlackApi;
use App\Ai\Slack\SlackConfiguration;
use App\Ai\Slack\SlackOutbox;
use App\Ai\Slack\SlackRateLimitException;
use App\Ai\Slack\SlackThreadService;
use App\Ai\Slack\SlackTimestamp;
use App\Ai\Slack\SlackTransportLock;
use App\Ai\Slack\SlackTurnAdmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class ProcessSlackEventJob implements ShouldQueue
{
    use Queueable;

    /**
     * The journal and maintenance scheduler own retries.
     */
    public int $tries = 1;

    /**
     * Process one durable ingress record.
     */
    public function __construct(/**
     * Ingress journal ID.
     */ public readonly int $eventId)
    {
        $this->onConnection('assistant');
        $this->onQueue('assistant');
    }

    /**
     * Import history and admit a message or a guarded decision on a background worker.
     */
    public function handle(): void
    {
        $event = DB::table('assistant_slack_events')->where('id', $this->eventId)->whereNull('processed_at')->where('available_at', '<=', \now())->first();
        $admin = Resolver::resolve(SlackConfiguration::class)->admin();
        if ($event === null || $admin === null) {
            return;
        }
        $lock = Resolver::resolve(SlackTransportLock::class)->lock('slack:thread:' . \hash('sha256', Typer::assertString($event->workspace_id) . ':' . Typer::assertString($event->channel_id) . ':' . Typer::assertString($event->thread_ts)), 180);
        if ($lock->get() !== true) {
            return;
        }
        $binding = null;
        try {
            if ($event->kind === 'message' && DB::table('assistant_slack_events')->where('workspace_id', $event->workspace_id)->where('channel_id', $event->channel_id)->where('thread_ts', $event->thread_ts)->where('kind', 'message')->whereNull('processed_at')->where('id', '<', $this->eventId)->exists()) {
                return;
            }
            $threads = Resolver::resolve(SlackThreadService::class);
            $binding = $threads->activate($event, $admin);
            if ($binding === null || SlackTimestamp::compare(Typer::assertString($event->message_ts), Typer::assertString($binding->activation_ts)) < 0) {
                $this->complete();

                return;
            }
            if (!Resolver::resolve(SlackApi::class)->isHuman(Typer::assertString($event->author_id))) {
                $this->complete();

                return;
            }
            $conversationId = Typer::assertString($binding->conversation_id);
            $conversation = Resolver::resolve(ConversationRepository::class)->findOwned($conversationId, $admin);
            if (!$conversation instanceof Conversation || $binding->admin_user_id !== $admin->getKey()) {
                $this->complete();

                return;
            }
            $payload = Typer::assertStringKeyArray(Typer::assertArray(\json_decode(Crypt::decryptString(Typer::assertString($event->payload)), true, flags: \JSON_THROW_ON_ERROR)));
            if ($event->kind === 'interaction') {
                $actions = Typer::assertArray($payload['actions'] ?? null);
                $action = Typer::assertArray($actions[0] ?? null);
                $value = Typer::assertArray(\json_decode(Typer::assertString($action['value'] ?? null), true, flags: \JSON_THROW_ON_ERROR));
                if (($value['action'] ?? null) === 'history_retry') {
                    $threads->importHistory($binding, $admin);
                    DB::table('assistant_slack_events')->where('workspace_id', $event->workspace_id)->where('channel_id', $event->channel_id)->where('thread_ts', $event->thread_ts)->whereNull('processed_at')->update(['available_at' => \now()]);
                } else {
                    $turns = Resolver::resolve(AssistantTurnService::class);
                    $target = $turns->findOwned(Typer::assertString($value['turn_id'] ?? null), $admin);
                    if ($target === null || $conversationId !== $target->getConversationId()) {
                        \abort(409);
                    }
                    $actionName = Typer::assertString($value['action'] ?? null);
                    if ($actionName === 'cancel') {
                        $turns->requestCancellation($target);
                    } elseif ($actionName === 'retry') {
                        Resolver::resolve(SlackTurnAdmission::class)->submit($admin, $conversation, $this->turnId(), $target->getKind(), $target->getInputPayload(), 'slack', Typer::assertString($event->author_id), $target);
                    } elseif (\in_array($actionName, ['approve', 'reject', 'select'], true)) {
                        Resolver::resolve(SlackTurnAdmission::class)->submit($admin, $conversation, $this->turnId(), 'decisions', ['decisions' => [Typer::assertString($value['tool_call_id'] ?? null) => ['action' => $actionName, 'option_id' => $value['option_id'] ?? null]]], 'slack', Typer::assertString($event->author_id));
                    }
                }
            } else {
                $threads->importHistory($binding, $admin);
                $message = '[Slack · ' . Typer::assertString($event->author_id) . '] ' . \str_replace('<@' . Config::inject()->assertString('services.slack.assistant.bot_user_id') . '>', '', Typer::assertString($payload['text'] ?? ''));
                Resolver::resolve(SlackTurnAdmission::class)->submit($admin, $conversation, $this->turnId(), 'message', ['message' => $message], 'slack', Typer::assertString($event->author_id));
            }
            $this->complete();
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 409) {
                $this->complete();
            } else {
                DB::table('assistant_slack_events')->where('id', $this->eventId)->update(['attempts' => DB::raw('attempts + 1'), 'error' => \mb_substr($exception->getMessage(), 0, 1000), 'available_at' => \now()->addSeconds(30), 'updated_at' => \now()]);
                \report($exception);
            }
        } catch (Throwable $exception) {
            $delay = $exception instanceof SlackRateLimitException ? $exception->retryAfter : \min(3600, 30 * (2 ** \min(7, Typer::assertInt($event->attempts))));
            DB::table('assistant_slack_events')->where('id', $this->eventId)->update(['attempts' => DB::raw('attempts + 1'), 'error' => \mb_substr($exception->getMessage(), 0, 1000), 'available_at' => \now()->addSeconds($delay), 'updated_at' => \now()]);
            if ($binding !== null && !(bool) $binding->history_ready) {
                DB::table('assistant_slack_threads')->where('id', $binding->id)->update(['history_error' => 'Slack thread history could not be loaded.', 'updated_at' => \now()]);
                $outbox = Resolver::resolve(SlackOutbox::class);
                $outbox->enqueue(Typer::assertString($binding->conversation_id), 'history-error', Typer::assertString(\__('Slack thread history could not be loaded. No request has been executed. Check access and retry.')), [$outbox->button(Typer::assertString(\__('Retry')), ['action' => 'history_retry'])]);
            }
            \report($exception);
        } finally {
            $lock->release();
            if (DB::table('assistant_slack_events')->where('id', $this->eventId)->whereNotNull('processed_at')->exists()) {
                $next = DB::table('assistant_slack_events')->where('workspace_id', $event->workspace_id)->where('channel_id', $event->channel_id)->where('thread_ts', $event->thread_ts)->whereNull('processed_at')->where('available_at', '<=', \now())->orderBy('id')->value('id');
                if ($next !== null) {
                    \dispatch(new self(Typer::assertInt($next)))->afterCommit();
                }
            }
        }
    }

    /**
     * Stable UUID derived from the durable event, also across worker restarts.
     */
    private function turnId(): string
    {
        $hex = \hash('sha256', 'stockflow-slack-event:' . $this->eventId);

        return \mb_substr($hex, 0, 8) . '-' . \mb_substr($hex, 8, 4) . '-4' . \mb_substr($hex, 13, 3) . '-a' . \mb_substr($hex, 17, 3) . '-' . \mb_substr($hex, 20, 12);
    }

    /**
     * Mark delivery consumed without retaining replay eligibility.
     */
    private function complete(): void
    {
        DB::table('assistant_slack_events')->where('id', $this->eventId)->update(['processed_at' => \now(), 'error' => null, 'updated_at' => \now()]);
    }
}
