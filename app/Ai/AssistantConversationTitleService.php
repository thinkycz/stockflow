<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\StockflowConversationTitleAgent;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class AssistantConversationTitleService
{
    /**
     * Replace a new conversation's immediate placeholder with an AI-generated title.
     */
    public function generate(
        Conversation $conversation,
        User $actor,
        string $userMessage,
        string $assistantMessage,
    ): void {
        if (!Config::inject()->assertBool('ai.conversations.generate_title') || !$this->isInitialPlaceholder($conversation, $userMessage)) {
            return;
        }

        try {
            $response = StockflowConversationTitleAgent::make()->prompt($this->prompt(
                $actor,
                $userMessage,
                $assistantMessage,
            ));
            $title = $this->sanitize($response->text);

            if ($title === null) {
                return;
            }

            $conversation->setAttribute('title', $title);
            $conversation->save();
        } catch (Throwable) {
            // Title generation is optional; the initial message remains a safe fallback.
        }
    }

    /**
     * Determine whether this is the first successful turn and the title is still its placeholder.
     */
    private function isInitialPlaceholder(Conversation $conversation, string $userMessage): bool
    {
        return $conversation->messages()->where('role', 'user')->count() === 1 &&
            Typer::assertString($conversation->getAttribute('title')) === Str::limit($userMessage, 100, preserveWords: true);
    }

    /**
     * Build a bounded excerpt for the title agent.
     */
    private function prompt(User $actor, string $userMessage, string $assistantMessage): string
    {
        return "Administrator locale: {$actor->getLocale()}\n"
            . "<user_message>\n" . Str::limit($userMessage, 500, preserveWords: true) . "\n</user_message>\n"
            . "<assistant_response>\n" . Str::limit($assistantMessage, 1000, preserveWords: true) . "\n</assistant_response>";
    }

    /**
     * Normalize model output into a safe single-line sidebar title.
     */
    private function sanitize(string $value): string|null
    {
        $title = Str::squish($value);
        $withoutHeading = \preg_replace('/^#{1,6}\\s*/u', '', $title);

        if (!\is_string($withoutHeading)) {
            return null;
        }

        $title = \mb_trim($withoutHeading, " \t\n\r\0\x0B\"'`“”„‘’");
        $title = \mb_rtrim($title, '.!?;:');

        return $title === '' ? null : Str::limit($title, 100, preserveWords: true);
    }
}
