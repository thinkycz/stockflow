<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Assistant;

use App\Ai\ConversationRepository;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;
use Thinkycz\LaravelCore\Support\Resolver;

class AssistantController
{
    /**
     * Show a new assistant conversation.
     */
    public function index(): Response
    {
        return $this->render(null);
    }

    /**
     * Show an owned assistant conversation.
     */
    public function show(string $conversation): Response
    {
        $user = User::mustAuth();
        $repository = Resolver::resolve(ConversationRepository::class);
        $owned = $repository->findOwned($conversation, $user);

        if (!$owned instanceof Conversation) {
            \abort(404);
        }

        return $this->render($owned);
    }

    /**
     * Delete an owned assistant conversation and its messages.
     */
    public function destroy(string $conversation): RedirectResponse
    {
        $user = User::mustAuth();
        $repository = Resolver::resolve(ConversationRepository::class);
        $owned = $repository->findOwned($conversation, $user);

        if (!$owned instanceof Conversation) {
            \abort(404);
        }

        $repository->delete($owned);

        return Resolver::resolveRedirector()->route('assistant.index');
    }

    /**
     * Render the assistant page payload.
     */
    private function render(Conversation|null $conversation): Response
    {
        $user = User::mustAuth();
        $repository = Resolver::resolve(ConversationRepository::class);

        return Inertia::render('assistant/Index', [
            'conversation' => $conversation instanceof Conversation ? $repository->assistantPayload($conversation, $user) : null,
            'conversations' => $repository->recentForSidebar($user),
        ]);
    }
}
