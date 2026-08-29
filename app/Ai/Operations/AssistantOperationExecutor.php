<?php

declare(strict_types=1);

namespace App\Ai\Operations;

use App\Models\User;

interface AssistantOperationExecutor
{
    /**
     * Validate an operation and build an exact side-effect-free preview.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function preview(string $identifier, User $actor, array $arguments): array;

    /**
     * Execute an approved operation through the shared application command.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(string $identifier, User $actor, array $arguments): array;
}
