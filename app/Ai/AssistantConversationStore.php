<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\AssistantTurn;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Storage\DatabaseConversationStore;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantConversationStore extends DatabaseConversationStore
{
    /**
     * Persist one logical user message across an original turn and every retry.
     */
    public function storeUserMessage(string $conversationId, string|null $participantType, int|string|null $participantId, AgentPrompt $prompt): string
    {
        $turnId = Context::get('assistant_turn_id');
        $turn = \is_string($turnId)
            ? AssistantTurn::query()->whereKey($turnId)->where('conversation_id', $conversationId)->first()
            : null;

        if (!$turn instanceof AssistantTurn || $turn->getParentTurnId() === null) {
            return parent::storeUserMessage($conversationId, $participantType, $participantId, $prompt);
        }

        $logical = Resolver::resolve(AssistantTurnService::class)->logicalUserMessage($turn);
        if ($logical === null) {
            return parent::storeUserMessage($conversationId, $participantType, $participantId, $prompt);
        }

        $message = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('content', $logical['message'])
            ->where('created_at', '>=', $logical['queued_at'])
            ->orderBy('id')
            ->first();

        if ($message instanceof ConversationMessage) {
            return Typer::assertString($message->getKey());
        }

        return parent::storeUserMessage($conversationId, $participantType, $participantId, $prompt);
    }
}
