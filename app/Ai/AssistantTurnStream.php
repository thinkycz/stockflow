<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thinkycz\LaravelCore\Support\Config;

final class AssistantTurnStream
{
    /**
     * Replay persisted events and follow the journal until the turn terminates.
     */
    public function response(AssistantTurn $turn, int $afterSequence = 0): StreamedResponse
    {
        return \response()->stream(function () use ($turn, $afterSequence): void {
            $sequence = \max(0, $afterSequence);
            $lastHeartbeat = \microtime(true);
            $deadline = \microtime(true) + Config::inject()->assertInt('ai.assistant.timeout_seconds') + 30;

            while (true) {
                $batch = AssistantTurnEvent::query()
                    ->where('turn_id', $turn->getTurnId())
                    ->where('sequence', '>', $sequence)
                    ->orderBy('sequence')
                    ->get();

                foreach ($batch as $event) {
                    $sequence = $event->getSequence();
                    echo 'id: ' . $sequence . "\n";
                    echo 'data: ' . \json_encode($event->getPayload(), \JSON_THROW_ON_ERROR) . "\n\n";
                    if (\ob_get_level() > 0) {
                        \ob_flush();
                    }
                    \flush();
                }

                $turn->refresh();

                if ($turn->getStatus()->terminal() && $batch->isEmpty()) {
                    echo 'id: ' . $sequence . "\n";
                    echo "data: [DONE]\n\n";

                    return;
                }

                if (\connection_aborted() === 1) {
                    return;
                }

                if ($deadline <= \microtime(true)) {
                    echo ": reconnect\n\n";

                    return;
                }

                if (\microtime(true) - $lastHeartbeat >= 10) {
                    echo ": heartbeat\n\n";
                    if (\ob_get_level() > 0) {
                        \ob_flush();
                    }
                    \flush();
                    $lastHeartbeat = \microtime(true);
                }

                \usleep(100000);
            }
        }, headers: [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'x-vercel-ai-ui-message-stream' => 'v1',
            'x-assistant-turn-id' => $turn->getTurnId(),
        ]);
    }
}
