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
            'store_id' => $schema->integer()->description('Optional owned store filter.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Optional result limit; defaults to 20.'),
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
