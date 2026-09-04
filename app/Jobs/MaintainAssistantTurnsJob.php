<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AssistantActionClassificationEnum;
use App\Enums\AssistantActionStatusEnum;
use App\Enums\AssistantTurnStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\AssistantTurn;
use App\Models\AssistantTurnEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Thinkycz\LaravelCore\Support\Config;

final class MaintainAssistantTurnsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maintenance is safe and deterministic in one attempt.
     */
    public int $tries = 1;

    /**
     * Prune expired events and fail abandoned nonterminal turns.
     */
    public function handle(): void
    {
        AssistantTurnEvent::query()
            ->where('created_at', '<', \now()->subDay())
            ->delete();

        $abandonedBefore = \now()->subSeconds(Config::inject()->assertInt('ai.assistant.timeout_seconds') + 60);

        AssistantActionAudit::query()->where('status', AssistantActionStatusEnum::RUNNING->value)
            ->where('classification', AssistantActionClassificationEnum::EXTERNAL_SIDE_EFFECT->value)
            ->where('updated_at', '<', $abandonedBefore)
            ->update(['status' => AssistantActionStatusEnum::UNCERTAIN->value, 'error_summary' => 'External outcome is uncertain; verify it before any new action.', 'completed_at' => \now()]);

        AssistantTurn::query()
            ->whereIn('status', [
                AssistantTurnStatusEnum::QUEUED->value,
                AssistantTurnStatusEnum::RUNNING->value,
                AssistantTurnStatusEnum::CANCEL_REQUESTED->value,
            ])
            ->where('updated_at', '<', $abandonedBefore)
            ->update([
                'status' => AssistantTurnStatusEnum::FAILED->value,
                'error_summary' => 'The durable assistant turn was abandoned before completion.',
                'completed_at' => \now(),
                'updated_at' => \now(),
            ]);
    }
}
