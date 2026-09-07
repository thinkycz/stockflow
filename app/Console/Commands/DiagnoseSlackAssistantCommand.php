<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Slack\SlackApi;
use App\Ai\Slack\SlackConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class DiagnoseSlackAssistantCommand extends Command
{
    /**
     * Read-only diagnostics; never publish a test message.
     */
    protected $signature = 'stockflow:slack-assistant:diagnose {--live : Verify token identity and channel/history access} {--channel=* : Stable channel IDs to verify}';

    /**
     * Explain the deployment check.
     */
    protected $description = 'Check Slack assistant configuration, journals and optional live read access without sending messages.';

    /**
     * Verify configuration and journals, optionally inspecting the controlled rollout channels.
     */
    public function handle(): int
    {
        if (Resolver::resolve(SlackConfiguration::class)->admin() === null) {
            $this->error('Slack assistant is disabled or missing its explicit administrator, workspace, bot ID, token or signing secret.');

            return self::FAILURE;
        }
        foreach (['assistant_slack_threads', 'assistant_slack_events', 'assistant_slack_inputs', 'assistant_decision_claims', 'assistant_slack_outbox'] as $table) {
            if (!Resolver::resolveSchemaBuilder()->hasTable($table)) {
                $this->error('Missing table: ' . $table);

                return self::FAILURE;
            }
        }
        $this->info('Configuration and transport tables are present.');
        $this->line('Pending incoming events: ' . DB::table('assistant_slack_events')->whereNull('processed_at')->count());
        $this->line('Incoming failures: ' . DB::table('assistant_slack_events')->whereNotNull('error')->count());
        $this->line('Pending outgoing posts: ' . DB::table('assistant_slack_outbox')->whereNull('sent_at')->count());
        $this->line('Outgoing failures: ' . DB::table('assistant_slack_outbox')->whereNotNull('error')->count());
        if ($this->option('live') !== true) {
            $this->warn('Live identity, scopes, channel access, public endpoints and workers have not been verified.');

            return self::SUCCESS;
        }
        try {
            $api = Resolver::resolve(SlackApi::class);
            $api->verifyIdentity();
            $channels = Typer::assertArray($this->option('channel'));
            if ($channels === []) {
                $this->error('Supply --channel for a store channel and a general channel.');

                return self::FAILURE;
            }
            foreach ($channels as $channel) {
                $channel = Typer::assertString($channel);
                $info = Typer::assertArray($api->call('conversations.info', ['channel' => $channel])['channel'] ?? null);
                if (($info['is_member'] ?? false) !== true || ($info['is_im'] ?? false) === true || ($info['is_mpim'] ?? false) === true) {
                    $this->error('Bot is not a member of a supported channel: ' . $channel);

                    return self::FAILURE;
                }
                $history = Typer::assertArray($api->call('conversations.history', ['channel' => $channel, 'limit' => 1])['messages'] ?? null);
                if ($history === []) {
                    $this->error('No existing message is available to verify thread history: ' . $channel);

                    return self::FAILURE;
                }
                $message = Typer::assertArray($history[0]);
                $api->history($channel, Typer::assertString($message['ts'] ?? null));
                $this->info('Channel membership and thread history verified: ' . $channel);
            }
            $this->warn('No messages were sent. Verify app subscriptions, interactivity, chat:write, public signed endpoint delivery and the assistant worker before the controlled test.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
