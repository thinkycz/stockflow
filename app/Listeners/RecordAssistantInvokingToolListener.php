<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\AssistantActionAuditService;
use Laravel\Ai\Events\InvokingTool;

final class RecordAssistantInvokingToolListener
{
    /**
     * Create the lifecycle listener.
     */
    public function __construct(
        private readonly AssistantActionAuditService $service,
    ) {}

    /**
     * Record the native tool invocation event.
     */
    public function handle(InvokingTool $event): void
    {
        $this->service->toolInvoking($event);
    }
}
