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
            'request' => $schema->anyOf([
                $schema->object([
                    'operation' => $schema->string()->enum(['list'])->required(),
                    'search' => $schema->string()->description('Optional worker-name search.'),
                    'attendance_rating_enabled' => $schema->boolean(),
                    'limit' => $schema->integer()->min(1)->max(50),
                    'cursor' => $schema->string(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'operation' => $schema->string()->enum(['summary'])->required(),
                    'search' => $schema->string()->description('Optional worker-name search.'),
                    'attendance_rating_enabled' => $schema->boolean(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'operation' => $schema->string()->enum(['detail'])->required(),
                    'id' => $schema->integer()->required(),
                ])->withoutAdditionalProperties(),
            ])->required(),
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
