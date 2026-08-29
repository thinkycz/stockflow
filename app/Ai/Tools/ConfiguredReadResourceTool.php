<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

final class ConfiguredReadResourceTool extends AbstractReadResourceTool
{
    /**
     * Create a read tool from catalog metadata.
     */
    public function __construct(
        User $actor,
        string $conversationId,
        private readonly string $toolName,
        private readonly string $resourceName,
        private readonly string $toolDescription,
        private readonly bool $searchable = false,
        private readonly bool $storeScoped = false,
    ) {
        parent::__construct($actor, $conversationId);
    }

    /**
     * Return the stable provider-facing tool name.
     */
    public function name(): string
    {
        return $this->toolName;
    }

    /**
     * Describe the bounded resource query to the model.
     */
    public function description(): string
    {
        return $this->toolDescription;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = [];

        if ($this->searchable) {
            $properties['search'] = $schema->string()->description('Optional resource-specific search.');
        }

        if ($this->storeScoped) {
            $properties['store_id'] = $schema->integer()->description('Optional owned store filter.');
        }

        $properties['limit'] = $schema->integer()->min(1)->max(50)->description('Optional result limit; defaults to 20.');

        return $properties;
    }

    /**
     * Return the query service resource identifier.
     */
    protected function resource(): string
    {
        return $this->resourceName;
    }
}
