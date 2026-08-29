<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\AssistantActionClassificationEnum;

interface AuditableAssistantTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string;

    /**
     * Business domain used by the supplemental audit.
     */
    public function auditDomain(): string;

    /**
     * Resolve the operation identifier from validated tool arguments.
     *
     * @param array<string, mixed> $arguments
     */
    public function auditOperation(array $arguments): string;

    /**
     * Resolve the action classification from tool arguments.
     *
     * @param array<string, mixed> $arguments
     */
    public function auditClassification(array $arguments): AssistantActionClassificationEnum;

    /**
     * Resolve bounded store and target metadata for the audit.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array{store_id: int|null, store_name: string|null, target_type: string|null, target_id: string|null}
     */
    public function auditContext(array $arguments): array;
}
