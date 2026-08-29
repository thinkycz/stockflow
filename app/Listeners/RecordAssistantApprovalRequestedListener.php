<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\AssistantActionAuditService;
use Laravel\Ai\Events\ToolApprovalRequested;

final class RecordAssistantApprovalRequestedListener
{
    /**
     * Create the lifecycle listener.
     */
    public function __construct(
        private readonly AssistantActionAuditService $service,
    ) {}

    /**
     * Record native Laravel AI approval proposals in the supplemental ledger.
     */
    public function handle(ToolApprovalRequested $event): void
    {
        $this->service->approvalRequested($event);
    }
}
