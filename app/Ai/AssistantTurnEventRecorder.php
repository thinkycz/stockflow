<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantTurnEventRecorder
{
    private const int TEXT_BATCH_CHARACTERS = 256;

    private const float TEXT_BATCH_SECONDS = 0.1;

    /**
     * Whether the native stream start event has already been journaled.
     */
    private bool $started = false;

    /**
     * Consecutive text deltas are journaled as one bounded batch.
     *
     * @var array<string, mixed>|null
     */
    private array|null $pendingTextDelta = null;

    /**
     * Turn owning the currently buffered text delta.
     */
    private string|null $pendingTextTurnId = null;

    /**
     * Last time a visible chunk was persisted for each native text part.
     *
     * @var array<string, float>
     */
    private array $lastTextFlushAt = [];

    /**
     * Persist one native Laravel AI event in Vercel stream format.
     */
    public function record(AssistantTurn $turn, StreamEvent $event, string|null $messageId = null): void
    {
        if ($event instanceof StreamStart) {
            if ($this->started) {
                return;
            }

            $this->started = true;
        }

        if ($event instanceof ToolApprovalRequest) {
            $this->flush($turn);

            foreach ($event->pendingApprovals as $approval) {
                $this->append($turn, [
                    'type' => 'tool-approval-request',
                    'toolCallId' => $approval->id,
                    'approvalId' => $approval->id,
                    'reason' => $approval->reason,
                ]);
            }

            return;
        }

        $payload = $event->toVercelProtocolArray();

        if ($payload === null) {
            return;
        }

        if (($payload['type'] ?? null) === 'start' && $messageId !== null) {
            $payload['messageId'] = $messageId;
        }

        if (($payload['type'] ?? null) === 'text-delta' && \is_string($payload['delta'] ?? null)) {
            $this->bufferTextDelta($turn, Typer::assertStringKeyArray($payload));

            return;
        }

        $this->flush($turn);
        $this->append($turn, Typer::assertStringKeyArray($payload));
    }

    /**
     * Persist any buffered text before a terminal or non-text event.
     */
    public function flush(AssistantTurn $turn): void
    {
        if ($this->pendingTextDelta === null || $this->pendingTextTurnId !== $turn->getTurnId()) {
            return;
        }

        $payload = $this->pendingTextDelta;
        $this->pendingTextDelta = null;
        $this->pendingTextTurnId = null;
        $this->append($turn, $payload);
        $this->lastTextFlushAt[Typer::assertString($payload['id'] ?? null)] = \microtime(true);
    }

    /**
     * Persist a safe user-facing terminal error event.
     */
    public function error(AssistantTurn $turn, string $message): void
    {
        $this->flush($turn);
        $this->append($turn, [
            'type' => 'error',
            'errorText' => \mb_substr($message, 0, 1000),
        ]);
    }

    /**
     * Coalesce adjacent deltas without delaying more than a small visible chunk.
     *
     * @param array<string, mixed> $payload
     */
    private function bufferTextDelta(AssistantTurn $turn, array $payload): void
    {
        $samePart = $this->pendingTextTurnId === $turn->getTurnId() &&
            ($this->pendingTextDelta['id'] ?? null) === ($payload['id'] ?? null);

        if (!$samePart) {
            $this->flush($turn);
            $this->pendingTextDelta = $payload;
            $this->pendingTextTurnId = $turn->getTurnId();
        } else {
            $this->pendingTextDelta['delta'] = Typer::assertString($this->pendingTextDelta['delta'] ?? null)
                . Typer::assertString($payload['delta'] ?? null);
        }

        $partId = Typer::assertString($this->pendingTextDelta['id'] ?? null);
        $lastFlushAt = $this->lastTextFlushAt[$partId] ?? null;

        if ($lastFlushAt === null ||
            self::TEXT_BATCH_CHARACTERS <= \mb_strlen(Typer::assertString($this->pendingTextDelta['delta'] ?? null)) ||
            self::TEXT_BATCH_SECONDS <= \microtime(true) - $lastFlushAt
        ) {
            $this->flush($turn);
        }
    }

    /**
     * Append one atomic encrypted event using the next turn sequence.
     *
     * @param array<string, mixed> $payload
     */
    private function append(AssistantTurn $turn, array $payload): void
    {
        $sequence = (Typer::parseNullableInt($turn->events()->max('sequence')) ?? 0) + 1;

        AssistantTurnEvent::query()->create([
            'turn_id' => $turn->getTurnId(),
            'sequence' => $sequence,
            'event_type' => Typer::parseNullableString($payload['type'] ?? null) ?? 'unknown',
            'payload' => $payload,
        ]);
    }
}
