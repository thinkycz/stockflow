<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

final class ReadWorkersTool extends AbstractReadResourceTool
{
    /**
     * Return the stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'read_workers';
    }

    /**
     * Describe the bounded worker query.
     */
    public function description(): string
    {
        return 'Read the main administrator’s workers with names, wage rates, attendance-rating state, and application links.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional worker-name search.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Optional result limit; defaults to 20.'),
        ];
    }

    /**
     * Return the query-service resource identifier.
     */
    protected function resource(): string
    {
        return 'workers';
    }
}
