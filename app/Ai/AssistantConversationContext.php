<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use App\Models\AssistantActionAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantConversationContext
{
    private const int SUMMARY_VERSION = 2;

    /**
     * @return list<Message>
     */
    public function recentMessages(string $conversationId): array
    {
        $maxRows = Config::inject()->assertInt('ai.assistant.context_max_rows');
        $fetchRows = \min(3000, $maxRows * 3);
        $this->assertStoredToolIntegrity($conversationId, $fetchRows);
        $messages = Resolver::resolve(ConversationStore::class)->getLatestConversationMessages($conversationId, $fetchRows);
        $groups = $this->semanticGroups($messages);
        $selected = [];
        $characters = 0;
        $rows = 0;
        $budget = Config::inject()->assertInt('ai.assistant.context_max_characters');

        foreach (\array_reverse($groups) as $group) {
            $groupCharacters = \mb_strlen(\serialize($group));
            $groupRows = \count($group);
            if ($selected !== [] && ($budget < $characters + $groupCharacters || $maxRows < $rows + $groupRows)) {
                break;
            }
            $selected[] = $group;
            $characters += $groupCharacters;
            $rows += $groupRows;
        }

        $context = $selected === [] ? [] : \array_merge(...\array_reverse($selected));
        $this->assertToolIntegrity($conversationId, $context);

        return $context;
    }

    /**
     * Return the current versioned rolling memory for a conversation.
     */
    public function summary(string $conversationId): string|null
    {
        $summary = DB::table('assistant_conversation_summaries')->where('conversation_id', $conversationId)->value('summary');

        return \is_string($summary) && $summary !== '' ? $summary : null;
    }

    /**
     * Rebuild rolling memory from old text and authoritative action outcomes.
     */
    public function refreshSummary(Conversation $conversation): void
    {
        $conversationId = Typer::assertString($conversation->getKey());
        $keep = Config::inject()->assertInt('ai.assistant.context_max_rows');
        $oldestRecentId = $conversation->messages()->orderByDesc('id')->skip(\max(0, $keep - 1))->value('id');
        if (!\is_string($oldestRecentId)) {
            return;
        }

        $memory = DB::table('assistant_conversation_summaries')->where('conversation_id', $conversationId)->first();
        $through = $memory !== null && \is_string($memory->through_message_id ?? null) ? $memory->through_message_id : null;
        $query = $conversation->messages()->where('id', '<', $oldestRecentId)->orderBy('id');
        if ($through !== null) {
            $query->where('id', '>', $through);
        }
        $older = $query->get();
        if ($older->isEmpty()) {
            return;
        }

        $callIds = [];
        foreach ($older as $message) {
            foreach (Typer::assertArray($message->getAttribute('tool_calls') ?? []) as $call) {
                if (\is_array($call) && \is_string($call['id'] ?? null)) {
                    $callIds[] = $call['id'];
                }
            }
        }
        $audits = AssistantActionAudit::query()->where('conversation_id', $conversationId)->whereIn('tool_call_id', $callIds)->get()->keyBy(static fn(AssistantActionAudit $audit): string => $audit->getToolCallId() ?? '');
        $lines = [];
        foreach ($older as $message) {
            $role = Typer::assertString($message->getAttribute('role'));
            $content = Typer::assertString($message->getAttribute('content'));
            if ($role === 'user' && $content !== '') {
                $lines[] = 'Administrator: ' . \mb_substr($content, 0, 1000);
            }
            foreach (Typer::assertArray($message->getAttribute('tool_calls') ?? []) as $call) {
                if (!\is_array($call) || !\is_string($call['id'] ?? null) || !\is_string($call['name'] ?? null)) {
                    continue;
                }
                $audit = $audits->get($call['id']);
                if (!$audit instanceof AssistantActionAudit || $audit->getClassification() === AssistantActionClassificationEnum::READ) {
                    continue;
                }
                $lines[] = match ($audit->getStatus()) {
                    AssistantActionStatusEnum::SUCCEEDED => 'Completed action: ' . $call['name'],
                    AssistantActionStatusEnum::REJECTED => 'Rejected action: ' . $call['name'],
                    AssistantActionStatusEnum::FAILED => 'Failed action: ' . $call['name'],
                    AssistantActionStatusEnum::PENDING_APPROVAL => 'Pending approval: ' . $call['name'],
                    AssistantActionStatusEnum::APPROVED,
                    AssistantActionStatusEnum::EDITED,
                    AssistantActionStatusEnum::RUNNING => 'Approved action in progress: ' . $call['name'],
                };
            }
        }

        $existing = $memory !== null && \is_string($memory->summary ?? null) ? $memory->summary : '';
        $summary = \mb_substr(\mb_trim($existing . "\n" . \implode("\n", $lines)), -100000);
        $last = Typer::assertInstance($older->last(), ConversationMessage::class);
        DB::table('assistant_conversation_summaries')->updateOrInsert(
            ['conversation_id' => $conversationId],
            ['version' => self::SUMMARY_VERSION, 'through_message_id' => Typer::assertString($last->getKey()), 'summary' => $summary, 'created_at' => $memory->created_at ?? \now(), 'updated_at' => \now()],
        );
    }

    /**
     * @param list<Message> $messages
     */
    public function assertToolIntegrity(string $conversationId, array $messages): void
    {
        $calls = [];
        $results = [];
        foreach ($messages as $message) {
            if ($message instanceof AssistantMessage) {
                foreach ($message->toolCalls as $call) { $calls[$call->id] = ($calls[$call->id] ?? 0) + 1; }
            }
            if ($message instanceof ToolResultMessage) {
                foreach ($message->toolResults as $result) { $results[$result->id] = ($results[$result->id] ?? 0) + 1; }
            }
        }
        $pending = $this->pendingApprovalIds($conversationId);
        foreach ($calls as $id => $count) {
            $resultCount = $results[$id] ?? 0;
            if ($count !== 1 || ($resultCount !== 1 && !\in_array($id, $pending, true))) {
                throw new RuntimeException('Assistant conversation history contains an incomplete tool interaction.');
            }
        }
        foreach ($results as $id => $count) {
            if ($count !== 1 || !isset($calls[$id])) {
                throw new RuntimeException('Assistant conversation history contains an unmatched tool result.');
            }
        }
    }

    /**
     * Verify raw stored rows before SDK reconstruction can omit malformed parts.
     */
    private function assertStoredToolIntegrity(string $conversationId, int $limit): void
    {
        $rows = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        while ($rows->isNotEmpty() && Typer::assertString($rows->first()->getAttribute('role')) !== 'user') {
            $rows->shift();
        }

        $calls = [];
        $results = [];
        $pending = [];
        foreach ($rows as $row) {
            foreach (Typer::assertArray($row->getAttribute('tool_calls') ?? []) as $call) {
                if (\is_array($call) && \is_string($call['id'] ?? null)) {
                    $calls[$call['id']] = ($calls[$call['id']] ?? 0) + 1;
                }
            }
            foreach (Typer::assertArray($row->getAttribute('tool_results') ?? []) as $result) {
                if (\is_array($result) && \is_string($result['id'] ?? null)) {
                    $results[$result['id']] = ($results[$result['id']] ?? 0) + 1;
                }
            }
            $state = $row->getAttribute('approval_state');
            if (\is_array($state) && \is_array($state['pending'] ?? null)) {
                foreach (\array_keys($state['pending']) as $id) {
                    if (\is_string($id)) {
                        $pending[$id] = true;
                    }
                }
            }
        }

        foreach ($calls as $id => $count) {
            $resultCount = $results[$id] ?? 0;
            if ($count !== 1 || ($resultCount !== 1 && !isset($pending[$id]))) {
                throw new RuntimeException('Assistant conversation storage contains an incomplete tool interaction.');
            }
        }
        foreach ($results as $id => $count) {
            if ($count !== 1 || !isset($calls[$id])) {
                throw new RuntimeException('Assistant conversation storage contains an unmatched tool result.');
            }
        }
    }

    /**
     * @param Collection<int, Message> $messages
     *
     * @return list<list<Message>>
     */
    private function semanticGroups(Collection $messages): array
    {
        $groups = [];
        foreach ($messages as $message) {
            if ($groups === [] || $message->role === MessageRole::User) { $groups[] = []; }
            $groups[\array_key_last($groups)][] = $message;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function pendingApprovalIds(string $conversationId): array
    {
        $ids = [];
        $rows = ConversationMessage::query()->where('conversation_id', $conversationId)->whereNotNull('approval_state')->get();
        foreach ($rows as $row) {
            $state = $row->getAttribute('approval_state');
            if (!\is_array($state) || !\is_array($state['pending'] ?? null)) { continue; }
            foreach (\array_keys($state['pending']) as $id) { if (\is_string($id)) { $ids[] = $id; } }
        }

        return \array_values(\array_unique($ids));
    }
}
