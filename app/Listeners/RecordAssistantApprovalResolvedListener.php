<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\AssistantActionAuditService;
use Laravel\Ai\Events\ToolApprovalResolved;

final class RecordAssistantApprovalResolvedListener
{
    /**
     * Create the lifecycle listener.
     */
    public function __construct(
        private readonly AssistantActionAuditService $service,
    ) {}

    /**
     * Record native Laravel AI approval decisions in the supplemental ledger.
     */
    public function handle(ToolApprovalResolved $event): void
    {
        $this->service->approvalResolved($event);
    }
}
