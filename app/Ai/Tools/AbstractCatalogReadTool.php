<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

abstract class AbstractCatalogReadTool extends AbstractReadResourceTool
{
    protected const string TOOL_NAME = '';

    protected const string TOOL_DESCRIPTION = '';

    protected const string RESOURCE = '';

    protected const bool SEARCHABLE = false;

    protected const bool STORE_SCOPED = false;

    protected const bool HAS_DETAIL = true;

    /**
     * Return the concrete provider-facing tool name.
     */
    final public function name(): string
    {
        return static::TOOL_NAME;
    }

    /**
     * Describe the concrete bounded resource query.
     */
    final public function description(): string
    {
        return static::TOOL_DESCRIPTION;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $filters = [];

        if (static::SEARCHABLE) {
            $filters['search'] = $schema->string()->description('Optional resource-specific search.');
        }

        if (static::STORE_SCOPED) {
            $filters['store_id'] = $schema->integer()->description('Optional owned store filter.');
        }

        $branches = [
            $schema->object([
                'operation' => $schema->string()->enum(['list'])->required(),
                ...$filters,
                'limit' => $schema->integer()->min(1)->max(50),
                'cursor' => $schema->string(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['summary'])->required(),
                ...$filters,
            ])->withoutAdditionalProperties(),
        ];

        if (static::HAS_DETAIL) {
            $branches[] = $schema->object([
                'operation' => $schema->string()->enum(['detail'])->required(),
                'id' => $schema->integer()->required(),
            ])->withoutAdditionalProperties();
        }

        return ['request' => $schema->anyOf($branches)->required()];
    }

    /**
     * Return the fixed query-service resource identifier.
     */
    final protected function resource(): string
    {
        return static::RESOURCE;
    }
}
