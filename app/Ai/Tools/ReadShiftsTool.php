<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

final class ReadShiftsTool extends AbstractReadResourceTool
{
    /**
     * Return the stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'read_shifts';
    }

    /**
     * Describe the bounded shift query.
     */
    public function description(): string
    {
        return 'Read scheduled shifts in owned stores with worker, date, time, duration, and application links.';
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
                    'store_id' => $schema->integer()->description('Optional owned store filter.'),
                    'worker_id' => $schema->integer(),
                    'year' => $schema->integer()->min(2000)->max(2100),
                    'month' => $schema->integer()->min(1)->max(12),
                    'date_from' => $schema->string(),
                    'date_to' => $schema->string(),
                    'limit' => $schema->integer()->min(1)->max(50),
                    'cursor' => $schema->string(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'operation' => $schema->string()->enum(['summary'])->required(),
                    'store_id' => $schema->integer()->description('Optional owned store filter.'),
                    'worker_id' => $schema->integer(),
                    'year' => $schema->integer()->min(2000)->max(2100),
                    'month' => $schema->integer()->min(1)->max(12),
                    'date_from' => $schema->string(),
                    'date_to' => $schema->string(),
                    'required_start_time' => $schema->string(),
                    'required_end_time' => $schema->string(),
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
        return 'shifts';
    }
}
