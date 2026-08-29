<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Assistant;

use App\Ai\AssistantTurnService;
use App\Ai\AssistantTurnStream;
use App\Ai\ConversationRepository;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Support\Resolver;

final class AssistantTurnStreamController
{
    /**
     * Replay and follow one owned durable turn journal.
     */
    public function __invoke(string $turn): Response
    {
        $user = User::mustAuth();
        $owned = Resolver::resolve(AssistantTurnService::class)->findOwned($turn, $user);

        if ($owned === null) {
            \abort(404);
        }

        if ($owned->events()->doesntExist() && $owned->getStatus()->terminal()) {
            return \response()->noContent();
        }

        $response = Resolver::resolve(AssistantTurnStream::class)->response($owned);
        $repository = Resolver::resolve(ConversationRepository::class);
        $conversation = $repository->findOwned($owned->getConversationId(), $user);

        if ($conversation !== null) {
            $response->headers->set('x-conversation-id', $owned->getConversationId());
            $response->headers->set('x-conversation-title', \rawurlencode($repository->sidebarSummary($conversation)['title']));
        }

        return $response;
    }
}
