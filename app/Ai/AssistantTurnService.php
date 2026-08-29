<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantTurnService
{
    private const int PARTIAL_RESPONSE_MAX_CHARACTERS = 100000;

    /**
     * Create an idempotent durable turn or return the matching existing submission.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{turn: AssistantTurn, created: bool}
     */
    public function createOrFind(User $actor, Conversation $conversation, string $id, string $kind, array $payload, string|null $parentTurnId = null, string $recoveryMode = 'normal'): array
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
                'parent_turn_id' => $parentTurnId,
                'kind' => $kind,
                'recovery_mode' => $recoveryMode,
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
            ->latest('created_at')
            ->first();

        if (!$turn instanceof AssistantTurn || !\in_array($turn->getStatus(), [
            AssistantTurnStatusEnum::QUEUED,
            AssistantTurnStatusEnum::RUNNING,
            AssistantTurnStatusEnum::CANCEL_REQUESTED,
            AssistantTurnStatusEnum::FAILED,
        ], true)) {
            return null;
        }

        if ($turn->getStatus() === AssistantTurnStatusEnum::FAILED && $this->hasCanonicalAssistantResponse($turn)) {
            return null;
        }

        return $turn;
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
     * @return array<string, mixed>
     */
    public function payload(AssistantTurn $turn): array
    {
        $logical = $this->logicalUserMessage($turn);
        $message = $logical['message'] ?? null;

        if ($logical !== null && $this->hasCanonicalUserMessage($logical['message'], $logical['queued_at'], $turn->getConversationId())) {
            $message = null;
        }

        return [
            'id' => $turn->getTurnId(),
            'status' => $turn->getStatus()->value,
            'kind' => $turn->getKind(),
            'recovery_mode' => $turn->getRecoveryMode(),
            'can_retry' => $turn->getStatus() === AssistantTurnStatusEnum::FAILED,
            'completed_actions' => $this->completedActions($turn),
            'failure' => $turn->getStatus() === AssistantTurnStatusEnum::FAILED ? [
                'code' => $this->completedActions($turn) === [] ? 'TURN_FAILED' : 'POST_ACTION_GENERATION_FAILED',
                'message' => $this->completedActions($turn) === []
                    ? 'The assistant response was interrupted. You can safely retry this turn.'
                    : 'The action completed, but the assistant response was interrupted. Continue without repeating the action.',
            ] : null,
            'message' => $message,
            'queued_at' => ($logical['queued_at'] ?? $turn->getQueuedAt())->toJSON(),
            'partial_response' => $turn->getStatus() === AssistantTurnStatusEnum::FAILED
                ? $this->partialResponse($turn)
                : null,
        ];
    }

    /**
     * Resolve the user-authored input shared by an original turn and its retries.
     *
     * @return array{message: string, queued_at: Carbon}|null
     */
    public function logicalUserMessage(AssistantTurn $turn): array|null
    {
        $root = $this->rootTurn($turn);
        $input = $root->getInputPayload();

        if ($root->getKind() !== 'message' || !\is_string($input['message'] ?? null)) {
            return null;
        }

        return [
            'message' => $input['message'],
            'queued_at' => $root->getQueuedAt(),
        ];
    }

    /**
     * Find native user rows duplicated by retries from the same logical turn.
     *
     * @return list<string>
     */
    public function duplicateCanonicalUserMessageIds(string $conversationId, User $actor): array
    {
        /** @var array<string, array{root: AssistantTurn, ended_at: Carbon}> $lineages */
        $lineages = [];

        foreach (AssistantTurn::query()
            ->where('conversation_id', $conversationId)
            ->where('actor_user_id', $actor->getKey())
            ->whereNotNull('parent_turn_id')
            ->orderBy('created_at')
            ->get() as $retry) {
            $retry = Typer::assertInstance($retry, AssistantTurn::class);
            $root = $this->rootTurn($retry);
            $endedAt = Typer::assertInstance(
                $retry->getAttribute('completed_at') ?? $retry->getAttribute('updated_at'),
                Carbon::class,
            );
            $current = $lineages[$root->getTurnId()] ?? null;

            if ($current === null || $current['ended_at']->isBefore($endedAt)) {
                $lineages[$root->getTurnId()] = ['root' => $root, 'ended_at' => $endedAt];
            }
        }

        $duplicates = [];

        foreach ($lineages as $lineage) {
            $logical = $this->logicalUserMessage($lineage['root']);
            if ($logical === null) {
                continue;
            }

            $ids = ConversationMessage::query()
                ->where('conversation_id', $conversationId)
                ->where('role', 'user')
                ->where('content', $logical['message'])
                ->whereBetween('created_at', [$logical['queued_at'], $lineage['ended_at']])
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn(mixed $id): string => Typer::assertString($id))
                ->all();

            $duplicates = [...$duplicates, ...\array_slice($ids, 1)];
        }

        return \array_values(\array_unique($duplicates));
    }

    /**
     * @return array{turn: AssistantTurn, created: bool}
     */
    public function retry(User $actor, Conversation $conversation, AssistantTurn $failed, string $newTurnId): array
    {
        if ($failed->getActorUserId() !== $actor->getKey() || $failed->getConversationId() !== Typer::assertString($conversation->getKey()) || $failed->getStatus() !== AssistantTurnStatusEnum::FAILED) {
            throw new InvalidArgumentException('Only an owned failed assistant turn can be retried.');
        }

        $completedActions = $this->completedActions($failed);
        if ($completedActions !== []) {
            return $this->createOrFind($actor, $conversation, $newTurnId, 'recovery', [
                'message' => "Continue after the previous response failed. The following mutations already succeeded and must not be proposed or executed again:\n" . \json_encode($completedActions, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE) . "\nExplain the completed outcome and continue with any remaining non-duplicate work.",
            ], $failed->getTurnId(), 'continuation_after_action');
        }

        return $this->createOrFind($actor, $conversation, $newTurnId, $failed->getKind(), $failed->getInputPayload(), $failed->getTurnId(), 'replay_without_action');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function completedActions(AssistantTurn $turn): array
    {
        return \array_values(AssistantActionAudit::query()
            ->where('turn_id', $turn->getTurnId())
            ->where('classification', AssistantActionClassificationEnum::MUTATION->value)
            ->where('status', AssistantActionStatusEnum::SUCCEEDED->value)
            ->orderBy('id')
            ->get()
            ->map(static fn(AssistantActionAudit $audit): array => [
                'tool' => $audit->getToolName(),
                'result' => Typer::assertStringKeyArray(Typer::assertArray($audit->getAttribute('result_summary') ?? [])),
            ])->all());
    }

    /**
     * Resolve the first attempt so every retry lineage owns one visible user message.
     */
    private function rootTurn(AssistantTurn $turn): AssistantTurn
    {
        $root = $turn;
        $visited = [$root->getTurnId() => true];

        while ($root->getParentTurnId() !== null) {
            $parent = AssistantTurn::query()
                ->whereKey($root->getParentTurnId())
                ->where('actor_user_id', $turn->getActorUserId())
                ->where('conversation_id', $turn->getConversationId())
                ->first();

            if (!$parent instanceof AssistantTurn || isset($visited[$parent->getTurnId()])) {
                break;
            }

            $root = $parent;
            $visited[$root->getTurnId()] = true;
        }

        return $root;
    }

    /**
     * Determine whether the SDK already stored the logical input for this retry lineage.
     */
    private function hasCanonicalUserMessage(string $message, Carbon $queuedAt, string $conversationId): bool
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('content', $message)
            ->where('created_at', '>=', $queuedAt)
            ->exists();
    }

    /**
     * A native response stored after admission is authoritative even if derived work failed later.
     */
    private function hasCanonicalAssistantResponse(AssistantTurn $turn): bool
    {
        foreach (ConversationMessage::query()
            ->where('conversation_id', $turn->getConversationId())
            ->where('role', 'assistant')
            ->where('created_at', '>=', $turn->getQueuedAt())
            ->get() as $message) {
            if (Typer::assertString($message->getAttribute('content')) !== '' ||
                Typer::assertArray($message->getAttribute('tool_calls') ?? []) !== [] ||
                Typer::assertArray($message->getAttribute('tool_results') ?? []) !== []
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reconstruct bounded visible text from the durable journal after a provider interruption.
     *
     * @return array{id: string, text: string, created_at: string}|null
     */
    private function partialResponse(AssistantTurn $turn): array|null
    {
        $text = '';
        $createdAt = null;

        foreach ($turn->events()->where('event_type', 'text-delta')->orderBy('sequence')->get() as $event) {
            $event = Typer::assertInstance($event, AssistantTurnEvent::class);
            $delta = Typer::parseNullableString($event->getPayload()['delta'] ?? null);

            if ($delta === null || $delta === '') {
                continue;
            }

            $createdAt ??= Typer::assertInstance($event->getAttribute('created_at'), Carbon::class);
            $text = \mb_substr($text . $delta, 0, self::PARTIAL_RESPONSE_MAX_CHARACTERS);

            if (\mb_strlen($text) >= self::PARTIAL_RESPONSE_MAX_CHARACTERS) {
                break;
            }
        }

        if ($text === '' || !$createdAt instanceof Carbon) {
            return null;
        }

        return [
            'id' => 'turn-' . $turn->getTurnId() . '-partial',
            'text' => $text,
            'created_at' => $createdAt->toJSON(),
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
