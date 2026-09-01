<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ConversationRepository
{
    public const int SIDEBAR_LIMIT = 50;

    /**
     * Resolve the most recently updated assistant conversations.
     *
     * @return list<array{id: string, title: string, updated_at: string|null}>
     */
    public function recentForSidebar(User $user, int $limit = self::SIDEBAR_LIMIT): array
    {
        $payload = [];

        foreach ($user->conversations()->select(['id', 'title', 'updated_at'])->orderByDesc('updated_at')->limit($limit)->get() as $conversation) {
            $payload[] = $this->sidebarSummary(Typer::assertInstance($conversation, Conversation::class));
        }

        return $payload;
    }

    /**
     * Serialize one conversation for the assistant sidebar.
     *
     * @return array{id: string, title: string, updated_at: string|null}
     */
    public function sidebarSummary(Conversation $conversation): array
    {
        $updatedAt = $conversation->getAttribute('updated_at');

        return [
            'id' => $this->conversationId($conversation),
            'title' => $this->title($conversation),
            'updated_at' => $updatedAt === null ? null : $this->serializeTimestamp($updatedAt),
        ];
    }

    /**
     * Find a conversation through the participant's official relationship.
     */
    public function findOwned(string $id, User $user): Conversation|null
    {
        $conversation = $user->conversations()->whereKey($id)->first();

        return $conversation instanceof Conversation ? $conversation : null;
    }

    /**
     * Delete a conversation and its SDK messages without touching assistant audits.
     */
    public function delete(Conversation $conversation): void
    {
        $conversationId = $this->conversationId($conversation);

        DB::transaction(static function () use ($conversation, $conversationId): void {
            $turnIds = DB::table('assistant_turns')
                ->where('conversation_id', $conversationId)
                ->pluck('id');
            DB::table('assistant_turn_events')->whereIn('turn_id', $turnIds)->delete();
            DB::table('assistant_turns')->where('conversation_id', $conversationId)->delete();
            DB::table('assistant_conversation_summaries')->where('conversation_id', $conversationId)->delete();
            $conversation->messages()->delete();
            $conversation->delete();
        });
    }

    /**
     * Serialize a conversation and stored messages for the Vercel Vue client.
     *
     * @return array<string, mixed>
     */
    public function assistantPayload(Conversation $conversation, User|null $actor = null): array
    {
        $actor ??= User::mustAuth();
        $turns = Resolver::resolve(AssistantTurnService::class);
        $conversationId = $this->conversationId($conversation);
        $duplicateUserMessageIds = $turns->duplicateCanonicalUserMessageIds($conversationId, $actor);
        $messages = [];

        foreach ($conversation->messages()->orderBy('id')->get() as $message) {
            if (\in_array(Typer::assertString($message->getKey()), $duplicateUserMessageIds, true)) {
                continue;
            }

            $messages[] = $this->messagePayload($message);
        }

        $turn = $turns->recoverableForConversation($conversationId, $actor);

        return [
            'id' => $conversationId,
            'title' => $this->title($conversation),
            'messages' => $messages,
            'active_turn' => $turn === null ? null : $turns->payload($turn),
        ];
    }

    /**
     * Resolve the latest message that still contains pending approvals.
     */
    public function latestPendingMessageId(Conversation $conversation): string|null
    {
        $message = $conversation->messages()
            ->whereNotNull('approval_state')
            ->orderByDesc('id')
            ->first();

        if (!$message instanceof ConversationMessage) {
            return null;
        }

        return ($this->arrayAttribute($message, 'approval_state')['pending'] ?? []) === []
            ? null
            : Typer::assertString($message->getKey());
    }

    /**
     * Find one unresolved stored tool call by provider ID.
     *
     * @return array{name: string, arguments: array<string, mixed>}|null
     */
    public function pendingToolCall(Conversation $conversation, string $toolCallId): array|null
    {
        foreach ($conversation->messages()->whereNotNull('approval_state')->orderByDesc('id')->get() as $message) {
            $pending = $this->arrayAttribute($message, 'approval_state')['pending'] ?? [];

            if (!\is_array($pending) || !\array_key_exists($toolCallId, $pending)) {
                continue;
            }

            foreach ($this->arrayAttribute($message, 'tool_calls') as $call) {
                if (!\is_array($call) || ($call['id'] ?? null) !== $toolCallId || !\is_string($call['name'] ?? null)) {
                    continue;
                }

                return [
                    'name' => $call['name'],
                    'arguments' => \is_array($call['arguments'] ?? null)
                        ? Typer::assertStringKeyArray($call['arguments'])
                        : [],
                ];
            }
        }

        return null;
    }

    /**
     * Resolve the conversation identifier.
     */
    public function conversationId(Conversation $conversation): string
    {
        return Typer::assertString($conversation->getKey());
    }

    /**
     * Serialize one stored SDK message as a Vercel UI message.
     *
     * @return array{id: string, role: string, metadata: array{created_at: string}, parts: list<array<string, mixed>>}
     */
    private function messagePayload(ConversationMessage $message): array
    {
        $role = Typer::assertString($message->getAttribute('role'));
        $content = Typer::assertString($message->getAttribute('content'));
        $parts = $content === '' ? [] : [['type' => 'text', 'text' => $content]];

        if ($role === 'assistant') {
            $parts = [...$parts, ...$this->toolParts($message)];
        }

        return [
            'id' => Typer::assertString($message->getKey()),
            'role' => $role,
            'metadata' => [
                'created_at' => $this->serializeTimestamp($message->getAttribute('created_at')),
            ],
            'parts' => $parts,
        ];
    }

    /**
     * Serialize stored tool calls, approval state, and tool results.
     *
     * @return list<array<string, mixed>>
     */
    private function toolParts(ConversationMessage $message): array
    {
        $results = [];

        foreach ($this->arrayAttribute($message, 'tool_results') as $result) {
            if (\is_array($result) && \is_string($result['id'] ?? null)) {
                $results[$result['id']] = $result;
            }
        }

        $approvalState = $this->arrayAttribute($message, 'approval_state');
        $pending = \is_array($approvalState['pending'] ?? null) ? $approvalState['pending'] : [];
        $parts = [];

        foreach ($this->arrayAttribute($message, 'tool_calls') as $call) {
            if (!\is_array($call) || !\is_string($call['id'] ?? null) || !\is_string($call['name'] ?? null)) {
                continue;
            }

            $id = $call['id'];
            $part = [
                'type' => 'tool-' . $call['name'],
                'toolCallId' => $id,
                'input' => \is_array($call['arguments'] ?? null) ? $call['arguments'] : [],
            ];

            if (\array_key_exists($id, $pending)) {
                $parts[] = [...$part, 'state' => 'approval-requested', 'approval' => [
                    'id' => $id,
                    'requestReason' => \is_string($pending[$id]) ? $pending[$id] : null,
                ]];

                continue;
            }

            $result = $results[$id] ?? null;

            if (!\is_array($result)) {
                $parts[] = [...$part, 'state' => 'input-available'];

                continue;
            }

            if (($result['denied'] ?? false) === true) {
                $parts[] = [...$part, 'state' => 'output-denied', 'approval' => [
                    'id' => $id,
                    'approved' => false,
                    'reason' => \is_string($result['result'] ?? null) ? $result['result'] : null,
                ]];

                continue;
            }

            $parts[] = [...$part, 'state' => 'output-available', 'output' => $result['result'] ?? null, 'approval' => [
                'id' => $id,
                'approved' => true,
            ]];
        }

        return $parts;
    }

    /**
     * Read an array-cast message attribute.
     *
     * @return array<array-key, mixed>
     */
    private function arrayAttribute(ConversationMessage $message, string $key): array
    {
        $value = $message->getAttribute($key);

        return \is_array($value) ? $value : [];
    }

    /**
     * Resolve a conversation title.
     */
    private function title(Conversation $conversation): string
    {
        return Typer::assertString($conversation->getAttribute('title'));
    }

    /**
     * Serialize a conversation timestamp.
     */
    private function serializeTimestamp(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        return Typer::assertString(Typer::assertInstance($value, Carbon::class)->toJSON());
    }
}
