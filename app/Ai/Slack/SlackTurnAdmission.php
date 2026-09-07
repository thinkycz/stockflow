<?php

declare(strict_types=1);

namespace App\Ai\Slack;

use App\Ai\AssistantDecisionGuard;
use App\Ai\AssistantTurnService;
use App\Ai\ConversationRepository;
use App\Enums\AssistantTurnStatusEnum;
use App\Jobs\RunAssistantTurnJob;
use App\Models\AssistantTurn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class SlackTurnAdmission
{
    /** Admit both interfaces under the same short database lock, independently of generation.
     * @param array<string, mixed> $payload
     *
     * @return array{turn: AssistantTurn, created: bool}
     */
    public function submit(User $actor, Conversation $conversation, string $turnId, string $kind, array $payload, string $origin, string $author, AssistantTurn|null $retry = null): array
    {
        return DB::transaction(function () use ($actor, $conversation, $turnId, $kind, $payload, $origin, $author, $retry): array {
            $id = Typer::assertString($conversation->getKey());
            $binding = DB::table('assistant_slack_threads')->where('conversation_id', $id)->whereNull('detached_at')->lockForUpdate()->first();
            if ($binding === null || $binding->admin_user_id !== $actor->getKey()) {
                \abort(404);
            }
            $turns = Resolver::resolve(AssistantTurnService::class);
            $existing = $turns->findOwned($turnId, $actor);
            if ($existing !== null) {
                if ($retry !== null && ($id !== $existing->getConversationId() || $existing->getParentTurnId() !== $retry->getTurnId())) {
                    \abort(409, 'The retry identifier belongs to a different submission.');
                }

                return $retry === null ? $turns->createOrFind($actor, $conversation, $turnId, $kind, $payload) : ['turn' => $existing, 'created' => false];
            }
            if ($kind === 'decisions' && $retry === null) {
                $decisions = Typer::assertStringKeyArray(Typer::assertArray($payload['decisions'] ?? null));
                Resolver::resolve(AssistantDecisionGuard::class)->decisions($conversation, $decisions);
                foreach ($decisions as $callId => $decision) {
                    if (Resolver::resolve(ConversationRepository::class)->pendingToolCall($conversation, $callId) === null || DB::table('assistant_decision_claims')->where('conversation_id', $id)->where('tool_call_id', $callId)->exists()) {
                        \abort(409, 'This decision has already been resolved.');
                    }
                    DB::table('assistant_decision_claims')->insert([
                        'conversation_id' => $id, 'tool_call_id' => $callId, 'turn_id' => $turnId,
                        'action' => Typer::assertArray($decision)['action'], 'option_id' => Typer::assertArray($decision)['option_id'] ?? null,
                        'origin' => $origin, 'author_id' => $author, 'created_at' => \now(), 'updated_at' => \now(),
                    ]);
                }
            }
            if ($retry !== null) {
                if (DB::table('assistant_decision_claims')->where('conversation_id', $id)->where('tool_call_id', 'retry:' . $retry->getTurnId())->exists()) {
                    \abort(409, 'This failed turn already has a retry.');
                }
                DB::table('assistant_decision_claims')->insert(['conversation_id' => $id, 'tool_call_id' => 'retry:' . $retry->getTurnId(), 'turn_id' => $turnId, 'origin' => $origin, 'author_id' => $author, 'created_at' => \now(), 'updated_at' => \now()]);
            }
            $submission = $retry === null
                ? $turns->createOrFind($actor, $conversation, $turnId, $kind, $payload)
                : $turns->retry($actor, $conversation, $retry, $turnId);
            DB::table('assistant_slack_inputs')->insert([
                'conversation_id' => $id, 'turn_id' => $turnId, 'origin' => $origin,
                'author_id' => $author, 'created_at' => \now(), 'updated_at' => \now(),
            ]);
            if ($origin === 'web' && $kind === 'message' && $retry === null) {
                Resolver::resolve(SlackOutbox::class)->enqueue($id, 'input:' . $turnId, 'Stockflow · ' . $actor->getEmail() . "\n" . Typer::assertString($payload['message'] ?? null));
            }
            $outbox = Resolver::resolve(SlackOutbox::class);
            $outbox->enqueue($id, 'queued:' . $turnId, Typer::assertString(\__('The assistant request is queued.')), [$outbox->button(Typer::assertString(\__('Cancel')), ['turn_id' => $turnId, 'action' => 'cancel'])]);
            \dispatch(new RunAssistantTurnJob($turnId))->afterCommit();

            return $submission;
        });
    }

    /**
     * Select the oldest runnable input; decisions jump ahead while messages remain durable.
     */
    public function next(Conversation $conversation): AssistantTurn|null
    {
        $id = Typer::assertString($conversation->getKey());
        if (AssistantTurn::query()->where('conversation_id', $id)->whereIn('status', ['running', 'cancel_requested'])->exists()) {
            return null;
        }
        $query = AssistantTurn::query()->where('conversation_id', $id)->where('status', AssistantTurnStatusEnum::QUEUED->value);
        if (Resolver::resolve(ConversationRepository::class)->latestPendingMessageId($conversation) !== null) {
            if ($this->decisions($conversation) === null) {
                return null;
            }
            $query->whereIn('kind', ['decisions', 'recovery']);
        }
        $turn = $query->orderByRaw('CASE WHEN kind = \'decisions\' THEN 0 ELSE 1 END')
            ->orderBy(DB::table('assistant_slack_inputs')->select('id')->whereColumn('turn_id', 'assistant_turns.id'))->first();

        return $turn instanceof AssistantTurn ? $turn : null;
    }

    /** Collect the complete native approval batch while each participant claims only their choice.
     * @return array<string, mixed>|null
     */
    public function decisions(Conversation $conversation): array|null
    {
        $payload = [];
        foreach ($conversation->messages()->whereNotNull('approval_state')->get() as $message) {
            $state = Typer::assertArray($message->getAttribute('approval_state'));
            foreach (Typer::assertStringKeyArray(Typer::assertArray($state['pending'] ?? [])) as $callId => $reason) {
                $claim = DB::table('assistant_decision_claims')->where('conversation_id', $conversation->getKey())->where('tool_call_id', $callId)->first();
                if ($claim === null) {
                    return null;
                }
                $payload[$callId] = ['action' => Typer::assertString($claim->action), 'option_id' => $claim->option_id];
            }
        }

        return $payload;
    }

    /** Close other submissions incorporated into the winning native batch, without running them again.
     * @param array<string, mixed> $decisions
     */
    public function finishDecisionBatch(AssistantTurn $leader, array $decisions): void
    {
        if ($leader->getKind() !== 'decisions' || $decisions === []) {
            return;
        }
        AssistantTurn::query()->where('conversation_id', $leader->getConversationId())->where('kind', 'decisions')->where('status', 'queued')->whereIn('id', DB::table('assistant_decision_claims')->select('turn_id')->where('conversation_id', $leader->getConversationId())->whereIn('tool_call_id', \array_keys($decisions)))->where('id', '!=', $leader->getTurnId())->update(['status' => 'completed', 'completed_at' => \now(), 'updated_at' => \now()]);
    }
}
