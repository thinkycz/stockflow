<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\StockflowAssistant;
use App\Ai\AssistantConversationContext;
use App\Ai\AssistantConversationLock;
use App\Ai\AssistantDecisionGuard;
use App\Ai\AssistantTurnEventRecorder;
use App\Ai\AssistantTurnService;
use App\Ai\ConversationRepository;
use App\Enums\AssistantTurnStatusEnum;
use App\Exceptions\AssistantTurnCancelledException;
use App\Models\AssistantTurn;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class RunAssistantTurnJob implements ShouldQueue
{
    use Queueable;

    /**
     * Provider work is attempted once; idempotent manual retry is explicit.
     */
    public int $tries = 1;

    /**
     * Worker timeout exceeds the provider generation limit.
     */
    public int $timeout = 150;

    /**
     * Create one durable turn runner.
     */
    public function __construct(
        /**
         * Stable durable turn identifier.
         */
        public readonly string $turnId,
        /**
         * Bounded conversation-lock admission attempt.
         */
        public readonly int $lockAttempt = 0,
    )
    {
        $this->onConnection('assistant');
        $this->onQueue('assistant');
    }

    /**
     * Execute and journal the native Laravel AI stream independently of HTTP.
     */
    public function handle(
        AssistantTurnService $turns,
        AssistantTurnEventRecorder $events,
        ConversationRepository $conversations,
        AssistantDecisionGuard $decisions,
        AssistantConversationLock $locks,
        AssistantConversationContext $context,
    ): void {
        $turn = AssistantTurn::query()->whereKey($this->turnId)->first();

        if (!$turn instanceof AssistantTurn || $turn->getStatus()->terminal()) {
            return;
        }

        $actor = User::query()->whereKey($turn->getActorUserId())->first();

        if (!$actor instanceof User) {
            $turns->transition($turn, AssistantTurnStatusEnum::FAILED, 'The assistant actor no longer exists.');

            return;
        }

        $conversation = $conversations->findOwned($turn->getConversationId(), $actor);

        if (!$conversation instanceof Conversation) {
            $turns->transition($turn, AssistantTurnStatusEnum::FAILED, 'The assistant conversation no longer exists.');

            return;
        }

        $lock = $locks->tryAcquire($turn->getConversationId());

        if ($lock === null) {
            if ($this->lockAttempt < 3) {
                \dispatch(new self($this->turnId, $this->lockAttempt + 1))->delay(\now()->addSecond());
            } else {
                $turns->transition($turn, AssistantTurnStatusEnum::FAILED, 'The conversation remained busy.');
            }

            return;
        }

        try {
            $turns->transition($turn, AssistantTurnStatusEnum::RUNNING);
            Context::add('assistant_turn_id', $turn->getTurnId());
            $input = $turn->getInputPayload();
            $prompt = match ($turn->getKind()) {
                'message', 'recovery' => Typer::assertString($input['message'] ?? null),
                'decisions' => $decisions->decisions($conversation, Typer::assertStringKeyArray(Typer::assertArray($input['decisions'] ?? null))),
                default => throw new InvalidArgumentException('Unknown assistant turn kind.'),
            };
            $pendingMessageId = $conversations->latestPendingMessageId($conversation);
            $context->recentMessages($turn->getConversationId());
            $response = StockflowAssistant::make(actor: $actor, assistantConversationId: $turn->getConversationId())
                ->continue($turn->getConversationId(), $actor)
                ->stream($prompt);
            $awaitingApproval = false;
            $lastStreamEnd = null;

            foreach ($response as $event) {
                if (!$event instanceof StreamEvent) {
                    continue;
                }

                $turn->refresh();

                if ($turn->getStatus() === AssistantTurnStatusEnum::CANCEL_REQUESTED) {
                    $events->flush($turn);
                    $turns->transition($turn, AssistantTurnStatusEnum::CANCELLED);

                    return;
                }

                if ($event instanceof StreamEnd) {
                    $lastStreamEnd = $event;

                    continue;
                }

                $events->record($turn, $event, $pendingMessageId);
                $awaitingApproval = $awaitingApproval || $event instanceof ToolApprovalRequest;
            }

            if ($lastStreamEnd instanceof StreamEnd) {
                $events->record($turn, $lastStreamEnd, $pendingMessageId);
            }

            $context->recentMessages($turn->getConversationId());

            $turns->transition(
                $turn,
                $awaitingApproval ? AssistantTurnStatusEnum::AWAITING_APPROVAL : AssistantTurnStatusEnum::COMPLETED,
            );
            $context->refreshSummary($conversation);
        } catch (AssistantTurnCancelledException) {
            $turns->transition($turn, AssistantTurnStatusEnum::CANCELLED);
        } catch (Throwable $exception) {
            $events->error($turn, 'AI assistant generation failed.');
            $turns->transition($turn, AssistantTurnStatusEnum::FAILED, $exception->getMessage());
            \report($exception);
        } finally {
            Context::forget('assistant_turn_id');
            $lock->release();
        }
    }
}
