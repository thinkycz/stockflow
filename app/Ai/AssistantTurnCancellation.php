<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\AssistantTurnStatusEnum;
use App\Exceptions\AssistantTurnCancelledException;
use App\Models\AssistantTurn;
use Illuminate\Support\Facades\Context;

final class AssistantTurnCancellation
{
    /**
     * Abort tool execution when the current durable turn was cancelled.
     */
    public function ensureNotRequested(): void
    {
        $turnId = Context::get('assistant_turn_id');

        if (!\is_string($turnId)) {
            return;
        }

        $turn = AssistantTurn::query()->whereKey($turnId)->first();

        if ($turn instanceof AssistantTurn && $turn->getStatus() === AssistantTurnStatusEnum::CANCEL_REQUESTED) {
            throw new AssistantTurnCancelledException('The assistant turn was cancelled.');
        }
    }
}
