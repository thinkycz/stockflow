<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\AssistantDataQueryService;
use App\Ai\AssistantTurnCancellation;
use App\Enums\AssistantActionClassificationEnum;
use App\Models\Store;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

abstract class AbstractReadResourceTool implements AuditableAssistantTool, Tool
{
    /**
     * Bind the read tool to its authorized actor and persisted conversation.
     */
    public function __construct(
        protected readonly User $actor,
        protected readonly string $conversationId,
    ) {}

    /**
     * Execute a fixed bounded resource query.
     */
    final public function handle(Request $request): string
    {
        Resolver::resolve(AssistantTurnCancellation::class)->ensureNotRequested();
        $arguments = Typer::assertStringKeyArray($request->all());
        $input = \is_array($arguments['request'] ?? null)
            ? Typer::assertStringKeyArray($arguments['request'])
            : $arguments;

        return \json_encode(
            Resolver::resolve(AssistantDataQueryService::class)->query($this->actor, [
                ...$input,
                'resource' => $this->resource(),
            ]),
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Use the fixed resource as the audit domain.
     */
    final public function auditDomain(): string
    {
        return $this->resource();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    final public function auditOperation(array $arguments): string
    {
        return $this->name();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    final public function auditClassification(array $arguments): AssistantActionClassificationEnum
    {
        return AssistantActionClassificationEnum::READ;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{store_id: int|null, store_name: string|null, target_type: string|null, target_id: string|null}
     */
    final public function auditContext(array $arguments): array
    {
        $storeId = Typer::parseNullableInt($arguments['store_id'] ?? null);
        $store = $storeId === null ? null : Store::query()
            ->where('user_id', $this->actor->resolveScopeUser()->getKey())
            ->whereKey($storeId)
            ->first();

        return [
            'store_id' => $store instanceof Store ? $store->getKey() : null,
            'store_name' => $store instanceof Store ? $store->getName() : null,
            'target_type' => $this->resource(),
            'target_id' => null,
        ];
    }

    /**
     * Return the fixed query-service resource identifier.
     */
    abstract protected function resource(): string;
}
