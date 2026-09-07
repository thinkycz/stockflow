<?php

declare(strict_types=1);

namespace App\Ai\Slack;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

final class SlackApi
{
    /** Call only the fixed Slack API host; callers persist retries outside domain execution.
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function call(string $method, array $parameters = []): array
    {
        $response = Http::withToken(Config::inject()->assertString('services.slack.notifications.bot_user_oauth_token'))
            ->timeout(20)->connectTimeout(5)->post('https://slack.com/api/' . $method, $parameters);
        if ($response->status() === 429) {
            throw new SlackRateLimitException(\max(1, (int) $response->header('Retry-After')));
        }
        $response->throw();
        $data = Typer::assertStringKeyArray(Typer::assertArray($response->json()));
        if (($data['ok'] ?? false) !== true) {
            throw new RuntimeException('Slack API: ' . Typer::assertString($data['error'] ?? 'unknown_error'));
        }

        return $data;
    }

    /**
     * Verify the configured bot token belongs to the configured workspace and bot user.
     */
    public function verifyIdentity(): void
    {
        $identity = $this->call('auth.test');
        if (($identity['team_id'] ?? null) !== Config::inject()->assertString('services.slack.assistant.workspace_id') || ($identity['user_id'] ?? null) !== Config::inject()->assertString('services.slack.assistant.bot_user_id')) {
            throw new RuntimeException('Slack token identity does not match the configured workspace and bot.');
        }
    }

    /**
     * Verify that the Slack user is a human, including messages missing a bot subtype.
     */
    public function isHuman(string $userId): bool
    {
        $response = $this->call('users.info', ['user' => $userId]);
        $user = Typer::assertArray($response['user'] ?? null);

        return ($user['is_bot'] ?? true) === false && ($user['is_app_user'] ?? false) !== true;
    }

    /** Fully paginate history before importing any rows or executing the activation.
     * @return list<array<string, mixed>>
     */
    public function history(string $channel, string $thread, string|null $scanId = null): array
    {
        $messages = [];
        $cursor = '';
        $seen = [];
        do {
            $page = $this->historyPage($scanId, 'conversations.replies', ['channel' => $channel, 'ts' => $thread, 'cursor' => $cursor, 'limit' => 100, 'include_all_metadata' => true]);
            foreach (Typer::assertArray($page['messages'] ?? null) as $message) {
                $messages[] = Typer::assertStringKeyArray(Typer::assertArray($message));
            }
            $metadata = Typer::assertArray($page['response_metadata'] ?? []);
            $cursor = Typer::assertString($metadata['next_cursor'] ?? '');
            if (($page['has_more'] ?? false) === true && $cursor === '') {
                throw new RuntimeException('Slack history pagination is incomplete.');
            }
            if ($cursor !== '' && isset($seen[$cursor])) {
                throw new RuntimeException('Slack history cursor repeated.');
            }
            $seen[$cursor] = true;
        } while ($cursor !== '');

        return $messages;
    }

    /** Read or persist one page so rate limits and restarts resume after completed pages.
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function historyPage(string|null $scanId, string $method, array $parameters): array
    {
        $key = \hash('sha256', ($scanId ?? '') . ':' . \json_encode($parameters, \JSON_THROW_ON_ERROR));
        $stored = $scanId === null ? null : DB::table('assistant_slack_history_pages')->where('page_key', $key)->value('payload');
        if (\is_string($stored)) {
            return Typer::assertStringKeyArray(Typer::assertArray(\json_decode(Crypt::decryptString($stored), true, flags: \JSON_THROW_ON_ERROR)));
        }
        $page = $this->call($method, $parameters);
        if ($scanId !== null) {
            DB::table('assistant_slack_history_pages')->insertOrIgnore(['page_key' => $key, 'scan_id' => $scanId, 'payload' => Crypt::encryptString(\json_encode($page, \JSON_THROW_ON_ERROR)), 'created_at' => \now(), 'updated_at' => \now()]);
        }

        return $page;
    }
}
