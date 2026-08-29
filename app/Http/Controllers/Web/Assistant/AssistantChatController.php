<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Assistant;

use App\Ai\Agents\StockflowAssistant;
use App\Ai\AssistantConversationLock;
use App\Ai\AssistantDecisionGuard;
use App\Ai\AssistantTurnService;
use App\Ai\AssistantTurnStream;
use App\Ai\ConversationRepository;
use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Jobs\RunAssistantTurnJob;
use App\Models\AssistantTurn;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Thinkycz\LaravelCore\Http\RequestSignature;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

class AssistantChatController
{
    use ThrottlesWebRequests;
    use ValidatesWebRequests;

    /**
     * Stream a new assistant turn or resume pending approvals.
     */
    public function __invoke(Request $request): Response
    {
        $promptMaxCharacters = Config::inject()->assertInt('ai.assistant.prompt_max_characters');
        $durableTurns = Config::inject()->assertBool('ai.assistant.durable_turns');
        $validated = $this->validateRequest($request, [
            'message' => ['nullable', 'string', 'max:' . $promptMaxCharacters, 'required_without:decisions', 'prohibited_with:decisions'],
            'conversation_id' => ['nullable', 'uuid', 'required_without:message'],
            'decisions' => ['nullable', 'array', 'min:1', 'required_without:message', 'prohibited_with:message'],
            'decisions.*' => ['required', 'array:action,option_id'],
            'decisions.*.action' => ['required', 'string', 'in:approve,reject,select'],
            'decisions.*.option_id' => ['nullable', 'string', 'max:100'],
            'turn_id' => [$durableTurns ? 'required' : 'nullable', 'uuid'],
        ]);

        $this->hit($this->limit());

        $user = User::mustAuth();
        $repository = Resolver::resolve(ConversationRepository::class);
        $conversationId = $validated->parseNullableString('conversation_id');
        $conversation = $conversationId === null ? null : $repository->findOwned($conversationId, $user);

        if ($conversationId !== null && !$conversation instanceof Conversation) {
            \abort(404);
        }

        $message = $validated->parseNullableString('message');

        if (!$conversation instanceof Conversation) {
            $conversationId = Resolver::resolve(ConversationStore::class)->storeConversation(
                Conversation::participantType($user),
                Conversation::participantKey($user),
                Str::limit(Typer::assertString($message), 100, preserveWords: true),
            );
            $conversation = $repository->findOwned($conversationId, $user);
        }

        $conversation = Typer::assertInstance($conversation, Conversation::class);
        $decisions = Typer::assertStringKeyArray($validated->parseArray('decisions'));

        if ($message === null) {
            Resolver::resolve(AssistantDecisionGuard::class)->decisions($conversation, $decisions);
        }

        if (!$durableTurns) {
            return $this->legacyStream($request, $user, $conversation, $conversationId, $message, $decisions, $repository);
        }

        $turnId = $validated->parseNullableString('turn_id') ?? Str::uuid()->toString();
        $turns = Resolver::resolve(AssistantTurnService::class);
        $active = $turns->activeForConversation($conversationId, $user);

        if ($active !== null && $turnId !== $active->getTurnId()) {
            \abort(409, 'This assistant conversation is already running in another tab.');
        }

        $submission = $turns->createOrFind(
            $user,
            $conversation,
            $turnId,
            $message === null ? 'decisions' : 'message',
            $message === null ? ['decisions' => $decisions] : ['message' => $message],
        );

        if (!$submission['created']) {
            return $this->durableResponse($submission['turn'], $conversation, $repository);
        }

        $admissionLock = Resolver::resolve(AssistantConversationLock::class)->tryAcquire($conversationId);

        if ($admissionLock === null) {
            $submission['turn']->delete();
            \abort(409, 'This assistant conversation is already running in another tab.');
        }

        $admissionLock->release();
        \dispatch(new RunAssistantTurnJob($turnId));

        return $this->durableResponse($submission['turn'], $conversation, $repository);
    }

    /**
     * Throttle assistant turns after validation succeeds.
     */
    protected function limit(RequestSignature|null $signature = null): Limit
    {
        $signature = $signature instanceof RequestSignature ? $signature : RequestSignature::default();

        return Limit::perMinute(Config::inject()->assertInt('ai.assistant.rate_limit_per_minute'))->by($signature->hash());
    }

    /**
     * Tail the durable journal while exposing canonical conversation headers.
     */
    private function durableResponse(
        AssistantTurn $turn,
        Conversation $conversation,
        ConversationRepository $repository,
    ): Response {
        $response = Resolver::resolve(AssistantTurnStream::class)->response($turn);
        $summary = $repository->sidebarSummary($conversation);
        $response->headers->set('x-conversation-id', $repository->conversationId($conversation));
        $response->headers->set('x-conversation-title', \rawurlencode($summary['title']));

        return $response;
    }

    /**
     * Keep the pre-migration transport available during the guarded production rollout.
     *
     * @param array<string, mixed> $decisions
     */
    private function legacyStream(
        Request $request,
        User $user,
        Conversation $conversation,
        string $conversationId,
        string|null $message,
        array $decisions,
        ConversationRepository $repository,
    ): Response {
        $prompt = $message ?? Resolver::resolve(AssistantDecisionGuard::class)->decisions($conversation, $decisions);
        $locks = Resolver::resolve(AssistantConversationLock::class);
        $lock = $locks->acquire($conversationId);

        try {
            $response = StockflowAssistant::make(actor: $user, assistantConversationId: $conversationId)
                ->continue($conversationId, $user)
                ->stream($prompt)
                ->usingVercelDataProtocol(true, $repository->latestPendingMessageId($conversation))
                ->toResponse($request);
        } catch (Throwable $exception) {
            $lock->release();

            throw $exception;
        }

        $response = Typer::assertInstance($response, StreamedResponse::class);
        $summary = $repository->sidebarSummary($conversation);
        $response->headers->set('x-conversation-id', $conversationId);
        $response->headers->set('x-conversation-title', \rawurlencode($summary['title']));

        return $locks->protect($response, $lock);
    }
}
