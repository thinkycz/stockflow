<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Assistant;

use App\Ai\AssistantConversationLock;
use App\Ai\AssistantTurnService;
use App\Ai\ConversationRepository;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Jobs\RunAssistantTurnJob;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class AssistantTurnRetryController
{
    use ValidatesWebRequests;

    /**
     * Create or reconnect to an idempotent recovery turn for one failed turn.
     */
    public function __invoke(Request $request, string $turn): Response
    {
        $validated = $this->validateRequest($request, ['turn_id' => ['required', 'uuid']]);
        $actor = User::mustAuth();
        $turns = Resolver::resolve(AssistantTurnService::class);
        $failed = $turns->findOwned($turn, $actor);
        if ($failed === null) {
            \abort(404);
        }

        $repository = Resolver::resolve(ConversationRepository::class);
        $conversation = $repository->findOwned($failed->getConversationId(), $actor);
        if (!$conversation instanceof Conversation) {
            \abort(404);
        }

        $lock = Resolver::resolve(AssistantConversationLock::class)->tryAcquire($failed->getConversationId());
        if ($lock === null) {
            \abort(409, 'This assistant conversation is already running in another tab.');
        }

        $shouldDispatch = false;

        try {
            if ($turns->activeForConversation($failed->getConversationId(), $actor) !== null) {
                \abort(409, 'This assistant conversation is already running in another tab.');
            }

            $submission = $turns->retry($actor, $conversation, $failed, Typer::assertString($validated->parseNullableString('turn_id')));
            $shouldDispatch = $submission['created'];
        } finally {
            $lock->release();
        }

        if ($shouldDispatch) {
            \dispatch(new RunAssistantTurnJob(
                turnId: $submission['turn']->getTurnId(),
                activeStoreId: ActiveStoreResolver::resolve($request, $actor)?->getKey(),
                browserSessionId: $request->session()->getId(),
            ));
        }

        return \response()->json([
            'turn_id' => $submission['turn']->getTurnId(),
            'recovery_mode' => $submission['turn']->getRecoveryMode(),
        ], $submission['created'] ? 202 : 200);
    }
}
