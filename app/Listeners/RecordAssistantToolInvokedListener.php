<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\AssistantActionAuditService;
use Laravel\Ai\Events\ToolInvoked;

final class RecordAssistantToolInvokedListener
{
    /**
     * Create the lifecycle listener.
     */
    public function __construct(
        private readonly AssistantActionAuditService $service,
    ) {}

    /**
     * Record the native successful tool event.
     */
    public function handle(ToolInvoked $event): void
    {
        $this->service->toolInvoked($event);
    }
}
