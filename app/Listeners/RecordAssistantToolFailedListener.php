<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\AssistantActionAuditService;
use Laravel\Ai\Events\ToolFailed;

final class RecordAssistantToolFailedListener
{
    /**
     * Create the lifecycle listener.
     */
    public function __construct(
        private readonly AssistantActionAuditService $service,
    ) {}

    /**
     * Record the native failed tool event.
     */
    public function handle(ToolFailed $event): void
    {
        $this->service->toolFailed($event);
    }
}
