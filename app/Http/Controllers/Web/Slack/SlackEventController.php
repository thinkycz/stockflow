<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Slack;

use App\Ai\Slack\SlackConfiguration;
use App\Ai\Slack\SlackIngress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

final class SlackEventController
{
    /**
     * Verify the untouched raw body before accepting events or interactive actions.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (Resolver::resolve(SlackConfiguration::class)->admin() === null) {
            \abort(503);
        }
        $timestamp = $request->header('X-Slack-Request-Timestamp', '');
        $signature = $request->header('X-Slack-Signature', '');
        if (!\ctype_digit($timestamp) || \abs(\time() - (int) $timestamp) > 300) {
            \abort(401);
        }
        $expected = 'v0=' . \hash_hmac('sha256', 'v0:' . $timestamp . ':' . $request->getContent(), Config::inject()->assertString('services.slack.assistant.signing_secret'));
        if (!\hash_equals($expected, $signature)) {
            \abort(401);
        }
        $raw = $request->input('payload');
        $payload = \is_string($raw) ? \json_decode($raw, true) : $request->json()->all();
        if (!\is_array($payload)) {
            \abort(400);
        }
        if (($payload['type'] ?? null) === 'url_verification') {
            if (!\is_string($payload['challenge'] ?? null)) {
                \abort(400);
            }

            return \response()->json(['challenge' => $payload['challenge']]);
        }
        $team = $payload['team_id'] ?? (\is_array($payload['team'] ?? null) ? ($payload['team']['id'] ?? null) : null);
        if ($team !== Config::inject()->assertString('services.slack.assistant.workspace_id')) {
            \abort(403);
        }
        Resolver::resolve(SlackIngress::class)->accept($payload);

        return \response()->json(['ok' => true]);
    }
}
