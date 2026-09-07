<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\ConversationRepository;
use App\Ai\Slack\SlackConfiguration;
use App\Ai\Slack\SlackOutbox;
use App\Ai\Slack\SlackTurnAdmission;
use App\Models\AssistantTurn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class MaintainSlackAssistantJob implements ShouldQueue
{
    use Queueable;

    /**
     * Recover committed work after a queue dispatch failure or worker restart.
     */
    public function handle(): void
    {
        $admin = Resolver::resolve(SlackConfiguration::class)->admin();
        if ($admin === null) {
            return;
        }
        DB::transaction(static function (): void {
            foreach (DB::table('assistant_slack_events')->whereNull('processed_at')->where('available_at', '<=', \now())->orderBy('id')->limit(100)->pluck('id') as $id) {
                \dispatch(new ProcessSlackEventJob(Typer::assertInt($id)))->afterCommit();
            }
            foreach (DB::table('assistant_slack_outbox')->whereNull('sent_at')->where('available_at', '<=', \now())->orderBy('id')->limit(100)->pluck('id') as $id) {
                \dispatch(new DeliverSlackMessageJob(Typer::assertInt($id)))->afterCommit();
            }
        });
        foreach (DB::table('assistant_slack_threads')->whereNull('detached_at')->where('history_ready', true)->where('admin_user_id', $admin->getKey())->pluck('conversation_id') as $id) {
            $conversation = Resolver::resolve(ConversationRepository::class)->findOwned(Typer::assertString($id), $admin);
            if (!$conversation instanceof Conversation) {
                continue;
            }
            foreach (AssistantTurn::query()->where('conversation_id', $id)->where('status', 'cancel_requested')->get() as $cancelled) {
                \dispatch(new RunAssistantTurnJob($cancelled->getTurnId()));
            }
            $next = Resolver::resolve(SlackTurnAdmission::class)->next($conversation);
            if ($next !== null) {
                \dispatch(new RunAssistantTurnJob($next->getTurnId()));
            }
            foreach (AssistantTurn::query()->where('conversation_id', $id)->where('updated_at', '>=', \now()->subDay())->get() as $turn) {
                Resolver::resolve(SlackOutbox::class)->publish($turn, $conversation, $admin);
            }
        }
    }
}
