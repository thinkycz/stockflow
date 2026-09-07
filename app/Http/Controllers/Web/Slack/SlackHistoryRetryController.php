<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Slack;

use App\Ai\ConversationRepository;
use App\Ai\Slack\SlackThreadService;
use App\Jobs\ProcessSlackEventJob;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class SlackHistoryRetryController
{
    /**
     * Retry failed history loading from the owned app conversation without provider work in HTTP.
     */
    public function __invoke(string $conversation): RedirectResponse
    {
        if (Resolver::resolve(ConversationRepository::class)->findOwned($conversation, User::mustAuth()) === null) {
            \abort(404);
        }
        $binding = Resolver::resolve(SlackThreadService::class)->binding($conversation);
        if ($binding === null) {
            \abort(404);
        }
        $events = DB::table('assistant_slack_events')->where('workspace_id', $binding->workspace_id)->where('channel_id', $binding->channel_id)->where('thread_ts', $binding->thread_ts)->whereNull('processed_at');
        DB::transaction(static function () use ($events): void {
            $events->update(['available_at' => \now()]);
            foreach ($events->pluck('id') as $id) {
                \dispatch(new ProcessSlackEventJob(Typer::assertInt($id)))->afterCommit();
            }
        });

        return Resolver::resolveRedirector()->route('assistant.conversations.show', $conversation);
    }
}
