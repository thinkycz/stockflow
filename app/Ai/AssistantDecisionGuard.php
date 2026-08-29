<?php

declare(strict_types=1);

namespace App\Ai;

use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantDecisionGuard
{
    /**
     * Create a decision guard backed by persisted conversation tool calls.
     */
    public function __construct(
        private readonly ConversationRepository $conversations,
    ) {}

    /**
     * Convert the minimal public decision contract into native SDK decisions.
     *
     * @param array<array-key, mixed> $payload
     */
    public function decisions(Conversation $conversation, array $payload): Decisions
    {
        $decisions = [];

        foreach ($payload as $toolCallId => $value) {
            $id = Typer::assertString($toolCallId);
            $decision = Typer::assertStringKeyArray(Typer::assertArray($value));
            $action = Typer::assertString($decision['action'] ?? null);
            $pending = $this->conversations->pendingToolCall($conversation, $id);

            if ($action === 'select') {
                $decisions[$id] = $this->selection($id, $decision, $pending);

                continue;
            }

            if (($decision['option_id'] ?? null) !== null) {
                $this->invalid($id, 'option_id', 'Only a clarification selection may include an option.');
            }

            if ($pending !== null && $pending['name'] === 'ask_user_choice') {
                $this->invalid($id, 'action', 'A clarification must be answered by selecting one of its options.');
            }

            $decisions[$id] = $action === 'approve'
                ? Decision::approve()
                : Decision::reject();
        }

        return Decisions::from($decisions);
    }

    /**
     * @param array<string, mixed> $decision
     * @param array{name: string, arguments: array<string, mixed>}|null $pending
     */
    private function selection(string $toolCallId, array $decision, array|null $pending): Decision
    {
        $optionId = Typer::parseNullableString($decision['option_id'] ?? null);

        if ($optionId === null || $optionId === '') {
            $this->invalid($toolCallId, 'option_id', 'Select one of the available options.');
        }

        if ($pending === null) {
            return Decision::edit(['selected_option_id' => $optionId]);
        }

        if ($pending['name'] !== 'ask_user_choice') {
            $this->invalid($toolCallId, 'action', 'Only clarification choices accept an option selection.');
        }

        $arguments = $pending['arguments'];
        $available = false;

        foreach (Typer::assertArray($arguments['options'] ?? null) as $option) {
            if (\is_array($option) && ($option['id'] ?? null) === $optionId) {
                $available = true;
                break;
            }
        }

        if (!$available) {
            $this->invalid($toolCallId, 'option_id', 'The selected option is no longer available.');
        }

        return Decision::edit([
            ...$arguments,
            'selected_option_id' => $optionId,
        ]);
    }

    /**
     * Reject one invalid public decision field through the standard error bag.
     */
    private function invalid(string $toolCallId, string $field, string $message): never
    {
        Thrower::default()
            ->message('decisions.' . $toolCallId . '.' . $field, \__($message))
            ->throw();
    }
}
