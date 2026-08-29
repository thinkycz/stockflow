<?php

declare(strict_types=1);

namespace App\Ai;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantConversationContext
{
    /**
     * Load complete recent semantic turns within configured row and character limits.
     *
     * @return list<Message>
     */
    public function recentMessages(string $conversationId): array
    {
        $messages = Resolver::resolve(ConversationStore::class)
            ->getLatestConversationMessages(
                $conversationId,
                Config::inject()->assertInt('ai.assistant.context_max_rows'),
            );
        $groups = $this->semanticGroups($messages);
        $selected = [];
        $characters = 0;
        $budget = Config::inject()->assertInt('ai.assistant.context_max_characters');

        foreach (\array_reverse($groups) as $group) {
            $groupCharacters = \mb_strlen(\serialize($group));

            if ($selected !== [] && $budget < $characters + $groupCharacters) {
                break;
            }

            $selected[] = $group;
            $characters += $groupCharacters;
        }

        return \array_merge(...\array_reverse($selected));
    }

    /**
     * Load the rolling summary for context that no longer fits in the recent window.
     */
    public function summary(string $conversationId): string|null
    {
        $summary = DB::table('assistant_conversation_summaries')
            ->where('conversation_id', $conversationId)
            ->value('summary');

        return \is_string($summary) && $summary !== '' ? $summary : null;
    }

    /**
     * Refresh deterministic older-context memory without retaining live tool values.
     */
    public function refreshSummary(Conversation $conversation): void
    {
        $keep = Config::inject()->assertInt('ai.assistant.context_max_rows');
        $older = $conversation->messages()
            ->orderByDesc('id')
            ->skip($keep)
            ->limit(2000)
            ->get()
            ->reverse()
            ->values();

        if ($older->isEmpty()) {
            return;
        }

        $lines = [];

        foreach ($older as $message) {
            $role = Typer::assertString($message->getAttribute('role'));
            $content = Typer::assertString($message->getAttribute('content'));

            if ($content !== '') {
                $lines[] = ($role === 'user' ? 'Administrator: ' : 'Assistant: ')
                    . \mb_substr($content, 0, 1000);
            }

            $calls = $message->getAttribute('tool_calls');
            $results = $message->getAttribute('tool_results');

            if (!\is_array($calls)) {
                continue;
            }

            foreach ($calls as $call) {
                if (!\is_array($call) || !\is_string($call['name'] ?? null)) {
                    continue;
                }

                $id = $call['id'] ?? null;
                $resolved = false;

                if (\is_array($results)) {
                    foreach ($results as $result) {
                        if (\is_array($result) && ($result['id'] ?? null) === $id) {
                            $resolved = true;

                            break;
                        }
                    }
                }

                $lines[] = ($resolved ? 'Completed action: ' : 'Proposed action: ') . $call['name'];
            }
        }

        $summary = \mb_substr(\implode("\n", $lines), -100000);
        $last = Typer::assertInstance($older->last(), ConversationMessage::class);
        $through = Typer::assertString($last->getKey());

        DB::table('assistant_conversation_summaries')->updateOrInsert(
            ['conversation_id' => Typer::assertString($conversation->getKey())],
            [
                'through_message_id' => $through,
                'summary' => $summary,
                'created_at' => \now(),
                'updated_at' => \now(),
            ],
        );
    }

    /**
     * Group messages so a tool call can never be separated from its result.
     *
     * @param Collection<int, Message> $messages
     *
     * @return list<list<Message>>
     */
    private function semanticGroups(Collection $messages): array
    {
        $groups = [];

        foreach ($messages as $message) {
            if ($groups === [] || $message->role === MessageRole::User) {
                $groups[] = [];
            }

            $groups[\array_key_last($groups)][] = $message;
        }

        return $groups;
    }
}
