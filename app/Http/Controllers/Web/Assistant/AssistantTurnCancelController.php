<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Assistant;

use App\Ai\AssistantTurnService;
use App\Models\User;
use Illuminate\Http\Response;
use Thinkycz\LaravelCore\Support\Resolver;

final class AssistantTurnCancelController
{
    /**
     * Request cancellation without undoing an already completed mutation.
     */
    public function __invoke(string $turn): Response
    {
        $turns = Resolver::resolve(AssistantTurnService::class);
        $owned = $turns->findOwned($turn, User::mustAuth());

        if ($owned === null) {
            \abort(404);
        }

        $turns->requestCancellation($owned);

        return \response()->noContent();
    }
}
