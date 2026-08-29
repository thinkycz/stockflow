<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Assistant;

use App\Ai\Agents\StockflowAssistant;
use App\Ai\AssistantConversationLock;
use App\Ai\AssistantDecisionGuard;
use App\Ai\ConversationRepository;
use App\Http\Controllers\Web\Concerns\ThrottlesWebRequests;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
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
        $validated = $this->validateRequest($request, [
            'message' => ['nullable', 'string', 'max:' . $promptMaxCharacters, 'required_without:decisions', 'prohibited_with:decisions'],
            'conversation_id' => ['nullable', 'uuid', 'required_without:message'],
            'decisions' => ['nullable', 'array', 'min:1', 'required_without:message', 'prohibited_with:message'],
            'decisions.*' => ['required', 'array:action,option_id'],
            'decisions.*.action' => ['required', 'string', 'in:approve,reject,select'],
            'decisions.*.option_id' => ['nullable', 'string', 'max:100'],
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
        $decisionPayload = $validated->parseArray('decisions');

        $prompt = $message ?? Resolver::resolve(AssistantDecisionGuard::class)->decisions($conversation, $decisionPayload);
        $lockService = Resolver::resolve(AssistantConversationLock::class);
        $lock = $lockService->acquire($conversationId);

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

        return $lockService->protect($response, $lock);
    }

    /**
     * Throttle assistant turns after validation succeeds.
     */
    protected function limit(RequestSignature|null $signature = null): Limit
    {
        $signature = $signature instanceof RequestSignature ? $signature : RequestSignature::default();

        return Limit::perMinute(Config::inject()->assertInt('ai.assistant.rate_limit_per_minute'))->by($signature->hash());
    }
}
