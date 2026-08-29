<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantTurn;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantTurnService
{
    /**
     * Create an idempotent durable turn or return the matching existing submission.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{turn: AssistantTurn, created: bool}
     */
    public function createOrFind(User $actor, Conversation $conversation, string $id, string $kind, array $payload): array
    {
        $hash = \hash('sha256', \json_encode($payload, \JSON_THROW_ON_ERROR));
        $existing = $this->findOwned($id, $actor);

        if ($existing instanceof AssistantTurn) {
            $this->assertSameSubmission($existing, $conversation, $hash);

            return ['turn' => $existing, 'created' => false];
        }

        try {
            $turn = DB::transaction(static fn(): AssistantTurn => AssistantTurn::query()->forceCreate([
                'id' => $id,
                'actor_user_id' => $actor->getKey(),
                'conversation_id' => Typer::assertString($conversation->getKey()),
                'kind' => $kind,
                'status' => AssistantTurnStatusEnum::QUEUED->value,
                'input_hash' => $hash,
                'input_payload' => $payload,
                'queued_at' => \now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            $turn = $this->findOwned($id, $actor);

            if (!$turn instanceof AssistantTurn) {
                throw new InvalidArgumentException('The assistant turn identifier is already in use.');
            }

            $this->assertSameSubmission($turn, $conversation, $hash);

            return ['turn' => $turn, 'created' => false];
        }

        return ['turn' => $turn, 'created' => true];
    }

    /**
     * Find a durable turn owned by the administrator.
     */
    public function findOwned(string $id, User $actor): AssistantTurn|null
    {
        $turn = AssistantTurn::query()
            ->whereKey($id)
            ->where('actor_user_id', $actor->getKey())
            ->first();

        return $turn instanceof AssistantTurn ? $turn : null;
    }

    /**
     * Find work that currently occupies one conversation execution slot.
     */
    public function activeForConversation(string $conversationId, User $actor): AssistantTurn|null
    {
        $turn = AssistantTurn::query()
            ->where('conversation_id', $conversationId)
            ->where('actor_user_id', $actor->getKey())
            ->whereIn('status', [
                AssistantTurnStatusEnum::QUEUED->value,
                AssistantTurnStatusEnum::RUNNING->value,
                AssistantTurnStatusEnum::CANCEL_REQUESTED->value,
            ])
            ->latest('created_at')
            ->first();

        return $turn instanceof AssistantTurn ? $turn : null;
    }

    /**
     * Find an active or failed turn that the browser can resume or retry.
     */
    public function recoverableForConversation(string $conversationId, User $actor): AssistantTurn|null
    {
        $turn = AssistantTurn::query()
            ->where('conversation_id', $conversationId)
            ->where('actor_user_id', $actor->getKey())
            ->whereIn('status', [
                AssistantTurnStatusEnum::QUEUED->value,
                AssistantTurnStatusEnum::RUNNING->value,
                AssistantTurnStatusEnum::CANCEL_REQUESTED->value,
                AssistantTurnStatusEnum::FAILED->value,
            ])
            ->latest('created_at')
            ->first();

        return $turn instanceof AssistantTurn ? $turn : null;
    }

    /**
     * Apply an allowed monotonic state transition and lifecycle timestamps.
     */
    public function transition(AssistantTurn $turn, AssistantTurnStatusEnum $status, string|null $error = null): void
    {
        $current = $turn->fresh()?->getStatus() ?? $turn->getStatus();

        $allowed = match ($current) {
            AssistantTurnStatusEnum::QUEUED => [
                AssistantTurnStatusEnum::RUNNING,
                AssistantTurnStatusEnum::CANCEL_REQUESTED,
                AssistantTurnStatusEnum::FAILED,
            ],
            AssistantTurnStatusEnum::RUNNING => [
                AssistantTurnStatusEnum::AWAITING_APPROVAL,
                AssistantTurnStatusEnum::COMPLETED,
                AssistantTurnStatusEnum::CANCEL_REQUESTED,
                AssistantTurnStatusEnum::CANCELLED,
                AssistantTurnStatusEnum::FAILED,
            ],
            AssistantTurnStatusEnum::CANCEL_REQUESTED => [
                AssistantTurnStatusEnum::CANCELLED,
                AssistantTurnStatusEnum::FAILED,
            ],
            AssistantTurnStatusEnum::AWAITING_APPROVAL,
            AssistantTurnStatusEnum::COMPLETED,
            AssistantTurnStatusEnum::CANCELLED,
            AssistantTurnStatusEnum::FAILED => [],
        };

        if (!\in_array($status, $allowed, true)) {
            return;
        }

        $attributes = ['status' => $status->value];

        if ($status === AssistantTurnStatusEnum::RUNNING) {
            $attributes['started_at'] = \now();
        }

        if ($status->terminal()) {
            $attributes['completed_at'] = \now();
            $attributes['input_payload'] = $status === AssistantTurnStatusEnum::FAILED
                ? $turn->getInputPayload()
                : [];
        }

        if ($error !== null) {
            $attributes['error_summary'] = \mb_substr($error, 0, 2000);
        }

        $turn->update($attributes);
        $turn->refresh();
    }

    /**
     * Mark a nonterminal turn for cooperative cancellation.
     */
    public function requestCancellation(AssistantTurn $turn): void
    {
        if ($turn->getStatus()->terminal()) {
            return;
        }

        $turn->update([
            'status' => AssistantTurnStatusEnum::CANCEL_REQUESTED->value,
            'cancel_requested_at' => \now(),
        ]);
        $turn->refresh();
    }

    /**
     * Build the safe frontend hydration payload for a recoverable turn.
     *
     * @return array{id: string, status: string, kind: string, message: string|null, queued_at: string}
     */
    public function payload(AssistantTurn $turn): array
    {
        $input = $turn->getInputPayload();

        return [
            'id' => $turn->getTurnId(),
            'status' => $turn->getStatus()->value,
            'kind' => $turn->getKind(),
            'message' => $turn->getKind() === 'message' && \is_string($input['message'] ?? null)
                ? $input['message']
                : null,
            'queued_at' => $turn->getQueuedAt()->toJSON(),
        ];
    }

    /**
     * Reject reuse of a client turn identifier with different input or conversation.
     */
    private function assertSameSubmission(AssistantTurn $turn, Conversation $conversation, string $hash): void
    {
        if ($turn->getConversationId() !== Typer::assertString($conversation->getKey()) || $hash !== $turn->getInputHash()) {
            throw new InvalidArgumentException('The assistant turn identifier cannot be reused for different input.');
        }
    }
}
