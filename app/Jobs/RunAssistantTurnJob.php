<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\StockflowAssistant;
use App\Ai\AssistantConversationContext;
use App\Ai\AssistantConversationLock;
use App\Ai\AssistantConversationTitleService;
use App\Ai\AssistantDecisionGuard;
use App\Ai\AssistantTurnEventRecorder;
use App\Ai\AssistantTurnService;
use App\Ai\ConversationRepository;
use App\Ai\Slack\SlackConfiguration;
use App\Ai\Slack\SlackOutbox;
use App\Ai\Slack\SlackThreadService;
use App\Ai\Slack\SlackTurnAdmission;
use App\Enums\AssistantTurnStatusEnum;
use App\Exceptions\AssistantTurnCancelledException;
use App\Models\AssistantTurn;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

final class RunAssistantTurnJob implements ShouldBeEncrypted, ShouldQueue
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
        /**
         * Active-store snapshot from the browser session that submitted the turn.
         */
        public readonly int|null $activeStoreId = null,
        /**
         * Encrypted queue context used only to update the originating browser session.
         */
        public readonly string|null $browserSessionId = null,
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
        AssistantConversationTitleService $titles,
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

        $binding = Resolver::resolve(SlackThreadService::class)->binding($turn->getConversationId());
        if ($binding !== null && (Resolver::resolve(SlackConfiguration::class)->admin()?->getKey() !== $actor->getKey() || !(bool) $binding->history_ready)) {
            return;
        }

        $lock = $locks->tryAcquire($turn->getConversationId());

        if ($lock === null) {
            if ($binding !== null) {
                return;
            }
            if ($this->lockAttempt < 3) {
                \dispatch(new self(
                    $this->turnId,
                    $this->lockAttempt + 1,
                    $this->activeStoreId,
                    $this->browserSessionId,
                ))->delay(\now()->addSecond());
            } else {
                $turns->transition($turn, AssistantTurnStatusEnum::FAILED, 'The conversation remained busy.');
            }

            return;
        }

        $nativeStreamCompleted = false;
        $decisionBatch = [];
        $completionStatus = AssistantTurnStatusEnum::COMPLETED;

        try {
            $freshTurn = $turn->fresh();
            if (!$freshTurn instanceof AssistantTurn || $conversation->fresh() === null) {
                return;
            }
            $turn = $freshTurn;
            if ($binding !== null) {
                $binding = Resolver::resolve(SlackThreadService::class)->binding($turn->getConversationId());
                if ($binding === null) { return; }
            }
            if ($turn->getStatus()->terminal()) {
                return;
            }
            if ($turn->getStatus() === AssistantTurnStatusEnum::CANCEL_REQUESTED) {
                $turns->transition($turn, AssistantTurnStatusEnum::CANCELLED);

                return;
            }
            if ($turn->getStatus() !== AssistantTurnStatusEnum::QUEUED) {
                return;
            }
            if ($binding !== null && Resolver::resolve(SlackTurnAdmission::class)->next($conversation)?->getTurnId() !== $turn->getTurnId()) {
                return;
            }
            $turns->transition($turn, AssistantTurnStatusEnum::RUNNING);
            Context::add('assistant_turn_id', $turn->getTurnId());
            Context::add(ActiveStoreResolver::SESSION_ID_CONTEXT, $binding === null ? $this->browserSessionId : null);
            Context::add('assistant_conversation_id', $turn->getConversationId());
            $input = $turn->getInputPayload();
            if ($binding !== null && $turn->getKind() === 'decisions') {
                $decisionBatch = Resolver::resolve(SlackTurnAdmission::class)->decisions($conversation) ?? [];
                $input['decisions'] = $decisionBatch;
            }
            $prompt = match ($turn->getKind()) {
                'message', 'recovery' => Typer::assertString($input['message'] ?? null),
                'decisions' => $decisions->decisions($conversation, Typer::assertStringKeyArray(Typer::assertArray($input['decisions'] ?? null))),
                default => throw new InvalidArgumentException('Unknown assistant turn kind.'),
            };
            $pendingMessageId = $conversations->latestPendingMessageId($conversation);
            $context->recentMessages($turn->getConversationId());
            $response = StockflowAssistant::make(
                actor: $actor,
                assistantConversationId: $turn->getConversationId(),
                activeStoreId: $binding === null ? $this->activeStoreId : Typer::assertNullableInt($binding->active_store_id),
            )
                ->continue($turn->getConversationId(), $actor)
                ->stream($prompt);
            $awaitingApproval = false;
            $lastStreamEnd = null;
            $textDeltas = [];

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

                if ($event instanceof TextDelta) {
                    $textDeltas[] = $event;
                }

                $events->record($turn, $event, $pendingMessageId);
                $awaitingApproval = $awaitingApproval || $event instanceof ToolApprovalRequest;
            }

            $nativeStreamCompleted = true;
            $completionStatus = $awaitingApproval
                ? AssistantTurnStatusEnum::AWAITING_APPROVAL
                : AssistantTurnStatusEnum::COMPLETED;

            if ($lastStreamEnd instanceof StreamEnd) {
                if (\in_array($turn->getKind(), ['message', 'recovery'], true)) {
                    $titles->generate(
                        $conversation,
                        $actor,
                        Typer::assertString($input['message'] ?? null),
                        TextDelta::combine($textDeltas),
                    );
                }

                $events->record($turn, $lastStreamEnd, $pendingMessageId);
            }

            $turns->transition($turn, $completionStatus);
            $context->recentMessages($turn->getConversationId());
            $context->refreshSummary($conversation);
        } catch (AssistantTurnCancelledException) {
            $turns->transition($turn, AssistantTurnStatusEnum::CANCELLED);
        } catch (Throwable $exception) {
            if ($nativeStreamCompleted) {
                $turns->transition($turn, $completionStatus);
                \report($exception);

                return;
            }

            $events->error($turn, 'AI assistant generation failed.');
            $turns->transition($turn, AssistantTurnStatusEnum::FAILED, $exception->getMessage());
            \report($exception);
        } finally {
            Context::forget('assistant_turn_id');
            Context::forget('assistant_conversation_id');
            Context::forget(ActiveStoreResolver::SESSION_ID_CONTEXT);
            if ($binding !== null && $turn->getStatus()->terminal()) {
                Resolver::resolve(SlackTurnAdmission::class)->finishDecisionBatch($turn, $decisionBatch);
            }
            $lock->release();
            if ($binding !== null && $turn->getStatus()->terminal()) {
                Resolver::resolve(SlackOutbox::class)->publish($turn, $conversation, $actor);
                $next = Resolver::resolve(SlackTurnAdmission::class)->next($conversation);
                if ($next !== null) {
                    \dispatch(new self($next->getTurnId()));
                }
            }
        }
    }
}
