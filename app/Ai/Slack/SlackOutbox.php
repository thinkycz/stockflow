<?php

declare(strict_types=1);

namespace App\Ai\Slack;

use App\Ai\AssistantTurnService;
use App\Ai\ConversationRepository;
use App\Enums\AssistantTurnStatusEnum;
use App\Jobs\DeliverSlackMessageJob;
use App\Models\AssistantTurn;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class SlackOutbox
{
    /** Journal bounded posts atomically, each with its own stable delivery key.
     * @param list<array<string, mixed>> $buttons
     */
    public function enqueue(string $conversationId, string $key, string $text, array $buttons = []): void
    {
        $text = \str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
        $text = \preg_replace('/\\[([^\\]\\n]+)\\]\\((https?:\\/\\/[^\\s)]+)\\)/', '<$2|$1>', $text) ?? $text;
        $chunks = \mb_str_split($text === '' ? 'Stockflow' : $text, 2800);
        DB::transaction(static function () use ($conversationId, $key, $chunks, $buttons): void {
            foreach ($chunks as $index => $chunk) {
                $deliveryKey = \hash('sha256', $conversationId . ':' . $key . ':' . $index);
                $blocks = [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $chunk]]];
                if ($index === \array_key_last($chunks) && $buttons !== []) {
                    foreach (\array_chunk($buttons, 5) as $group) {
                        $blocks[] = ['type' => 'actions', 'elements' => $group];
                    }
                }
                DB::table('assistant_slack_outbox')->insertOrIgnore([
                    'conversation_id' => $conversationId, 'delivery_key' => $deliveryKey,
                    'payload' => Crypt::encryptString(\json_encode(['text' => $chunk, 'blocks' => $blocks, 'unfurl_links' => false, 'unfurl_media' => false], \JSON_THROW_ON_ERROR)),
                    'available_at' => \now(), 'created_at' => \now(), 'updated_at' => \now(),
                ]);
                $id = DB::table('assistant_slack_outbox')->where('delivery_key', $deliveryKey)->whereNull('sent_at')->value('id');
                if ($id === null) { continue; }
                \dispatch(new DeliverSlackMessageJob(Typer::assertInt($id)))->afterCommit();
            }
        });
    }

    /**
     * Render terminal canonical text and existing validated approval previews without running tools.
     */
    public function publish(AssistantTurn $turn, Conversation $conversation, User $actor): void
    {
        if (!$turn->getStatus()->terminal()) {
            return;
        }
        $text = '';
        foreach ($turn->events()->where('event_type', 'text-delta')->orderBy('sequence')->get() as $event) {
            $text .= Typer::assertString($event->getPayload()['delta'] ?? '');
        }
        if ($text !== '') {
            $this->enqueue($turn->getConversationId(), 'response:' . $turn->getTurnId(), 'Stockflow · ' . Typer::assertString(\__('Assistant')) . "\n" . $text);
        }
        foreach ($turn->events()->where('event_type', 'tool-output-available')->orderBy('sequence')->get() as $event) {
            $payload = $event->getPayload();
            $output = $payload['output'] ?? null;
            if (\is_string($output)) {
                $output = \json_decode($output, true);
            }
            if (!\is_array($output)) { continue; }
            $record = \is_array($output['record'] ?? null) ? $output['record'] : [];
            $resultText = '';
            if (($output['status'] ?? null) === 'succeeded') {
                $resultText = Typer::assertString(\__('Action completed.'));
                if (\is_string($record['url'] ?? null) && \preg_match('/^https?:\\/\\//', $record['url']) === 1) {
                    $resultText .= "\n[" . Typer::assertString(\__('Open result')) . '](' . $record['url'] . ')';
                }
            } elseif (\is_int($output['returned_count'] ?? null)) {
                $resultText = Typer::assertString(\__(($output['complete'] ?? false) === true ? 'Read completed: :count records.' : 'Partial read: :count records. More data may remain.', ['count' => $output['returned_count']]));
            }
            if ($resultText !== '') {
                $this->enqueue($turn->getConversationId(), 'result:' . Typer::assertString($payload['toolCallId'] ?? '') . ':' . $turn->getTurnId(), $resultText);
            }
        }
        foreach ($conversation->messages()->whereNotNull('approval_state')->orderBy('id')->get() as $message) {
            $state = Typer::assertArray($message->getAttribute('approval_state'));
            foreach (Typer::assertStringKeyArray(Typer::assertArray($state['pending'] ?? [])) as $callId => $reason) {
                if (DB::table('assistant_decision_claims')->where('conversation_id', $turn->getConversationId())->where('tool_call_id', $callId)->exists()) {
                    continue;
                }
                $pending = Resolver::resolve(ConversationRepository::class)->pendingToolCall($conversation, $callId);
                if ($pending === null) {
                    continue;
                }
                $buttons = [];
                if ($pending['name'] === 'ask_user_choice') {
                    $preview = Typer::assertString($pending['arguments']['question'] ?? null);
                    foreach (Typer::assertArray($pending['arguments']['options'] ?? null) as $option) {
                        $option = Typer::assertArray($option);
                        $buttons[] = $this->button(Typer::assertString($option['label'] ?? null), ['turn_id' => $turn->getTurnId(), 'tool_call_id' => $callId, 'action' => 'select', 'option_id' => $option['id'] ?? null]);
                    }
                } else {
                    $preview = $this->preview(Typer::assertString($reason));
                    foreach (['approve' => Typer::assertString(\__('Approve')), 'reject' => Typer::assertString(\__('Reject'))] as $action => $label) {
                        $buttons[] = $this->button($label, ['turn_id' => $turn->getTurnId(), 'tool_call_id' => $callId, 'action' => $action]);
                    }
                }
                $this->enqueue($turn->getConversationId(), 'approval:' . $callId, $preview, $buttons);
            }
        }
        if ($turn->getStatus() === AssistantTurnStatusEnum::FAILED) {
            $payload = Resolver::resolve(AssistantTurnService::class)->payload($turn);
            $buttons = ($payload['can_retry'] ?? false) === true ? [$this->button(Typer::assertString(\__('Retry')), ['turn_id' => $turn->getTurnId(), 'action' => 'retry'])] : [];
            $this->enqueue($turn->getConversationId(), 'failure:' . $turn->getTurnId(), Typer::assertString(\__('The assistant response failed. Open Stockflow to inspect completed actions before continuing.')), $buttons);
        }
        if ($turn->getStatus() === AssistantTurnStatusEnum::CANCELLED) {
            $this->enqueue($turn->getConversationId(), 'cancelled:' . $turn->getTurnId(), Typer::assertString(\__('Assistant turn cancelled.')));
        }
    }

    /** One bounded interactive button carrying only application-owned identifiers.
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    public function button(string $label, array $value): array
    {
        return ['type' => 'button', 'action_id' => 'stockflow_' . \mb_substr(\hash('sha256', \json_encode($value, \JSON_THROW_ON_ERROR)), 0, 24), 'text' => ['type' => 'plain_text', 'text' => \mb_substr($label, 0, 75)], 'value' => \json_encode($value, \JSON_THROW_ON_ERROR)];
    }

    /**
     * Render the shared presenter's public contract; never reconstruct a mutation preview.
     */
    private function preview(string $reason): string
    {
        $data = \json_decode($reason, true);
        if (!\is_array($data)) {
            return $reason;
        }
        $params = Typer::assertArray($data['summary_params'] ?? []);
        $summaryKey = Typer::assertString($data['summary_key'] ?? 'Confirm the proposed action');
        $replacements = [];
        foreach ($params as $key => $value) {
            if (\is_string($key) && (\is_string($value) || \is_int($value) || \is_float($value))) {
                $replacements[$key] = $value;
            }
        }
        $text = Typer::assertString(\__($summaryKey, $replacements)) . "\n";
        foreach ($params as $key => $value) {
            if (\is_scalar($value)) {
                $text .= $key . ': ' . (string) $value . "\n";
            }
        }
        foreach (Typer::assertArray($data['business_rows'] ?? []) as $row) {
            if (\is_array($row)) {
                $text .= \implode(' · ', \array_map(static fn(mixed $value): string => \is_scalar($value) ? (string) $value : '', $row)) . "\n";
            }
        }

        return $text;
    }
}
