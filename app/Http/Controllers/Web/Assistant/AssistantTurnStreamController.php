<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Assistant;

use App\Ai\AssistantTurnService;
use App\Ai\AssistantTurnStream;
use App\Ai\ConversationRepository;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Support\Resolver;

final class AssistantTurnStreamController
{
    /**
     * Replay and follow one owned durable turn journal.
     */
    public function __invoke(Request $request, string $turn): Response
    {
        $user = User::mustAuth();
        $owned = Resolver::resolve(AssistantTurnService::class)->findOwned($turn, $user);

        if ($owned === null) {
            \abort(404);
        }

        if ($owned->events()->doesntExist() && $owned->getStatus()->terminal()) {
            return \response()->noContent();
        }

        $lastEventId = $request->headers->get('Last-Event-ID');
        $response = Resolver::resolve(AssistantTurnStream::class)->response($owned, \is_string($lastEventId) && \ctype_digit($lastEventId) ? (int) $lastEventId : 0);
        $repository = Resolver::resolve(ConversationRepository::class);
        $conversation = $repository->findOwned($owned->getConversationId(), $user);

        if ($conversation !== null) {
            $response->headers->set('x-conversation-id', $owned->getConversationId());
            $response->headers->set('x-conversation-title', \rawurlencode($repository->sidebarSummary($conversation)['title']));
        }

        return $response;
    }
}
