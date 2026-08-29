<?php

declare(strict_types=1);

use App\Jobs\PruneAssistantActionAuditsJob;
use App\Models\AssistantActionAudit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

\test('prune job removes assistant action audits older than ninety Prague days', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-28 04:30', 'Europe/Prague'));
    [$admin] = \createIsolatedUserWithWarehouse();

    AssistantActionAudit::factory()->create([
        'actor_user_id' => $admin->getKey(),
        'proposed_at' => '2026-05-29T21:59:59+00:00',
    ]);
    AssistantActionAudit::factory()->create([
        'actor_user_id' => $admin->getKey(),
        'proposed_at' => '2026-05-29T22:00:00+00:00',
    ]);

    $job = new PruneAssistantActionAuditsJob();
    $job->handle();

    \expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and(AssistantActionAudit::query()->count())->toBe(1)
        ->and(AssistantActionAudit::query()->sole()->getProposedAt()->toIso8601String())->toBe('2026-05-29T22:00:00+00:00');
});
