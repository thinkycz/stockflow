<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\AssistantActionPresenter;
use App\Ai\Operations\AssistantOperationExecutor;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

abstract class AbstractExecutorBackedResourceTool extends AbstractApprovableResourceTool
{
    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{store_id: int|null, store_name: string|null, target_type: string|null, target_id: string|null}
     */
    final public function auditContext(array $arguments): array
    {
        $preview = $this->legacyPreview($arguments);
        $store = $preview['store'] ?? null;
        $target = $preview['target'] ?? null;

        return [
            'store_id' => \is_array($store) ? Typer::parseNullableInt($store['id'] ?? null) : null,
            'store_name' => \is_array($store) ? Typer::parseNullableString($store['name'] ?? null) : null,
            'target_type' => \is_array($target) ? Typer::parseNullableString($target['type'] ?? null) : null,
            'target_id' => \is_array($target) ? Typer::parseNullableString($target['id'] ?? null) : null,
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    final protected function preview(array $arguments): array
    {
        return Resolver::resolve(AssistantActionPresenter::class)->present(
            $arguments,
            $this->legacyPreview($arguments),
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    final protected function execute(array $arguments): array
    {
        return $this->executor()->execute($this->action($arguments), $this->actor, $this->legacyArguments($arguments));
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    final protected function legacyArguments(array $arguments): array
    {
        $request = $this->request($arguments);

        return [
            'operation' => $this->action($arguments),
            'store_id' => Typer::parseNullableInt($request['store_id'] ?? null),
            'target_id' => Typer::parseNullableInt($request['target_id'] ?? null),
            'context_json' => $this->encode(Typer::assertStringKeyArray(Typer::assertArray($request['context'] ?? []))),
            'values_json' => $this->encode($this->values($arguments)),
        ];
    }

    /**
     * Return the human-service adapter for this resource family.
     */
    abstract protected function executor(): AssistantOperationExecutor;

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function legacyPreview(array $arguments): array
    {
        return $this->executor()->preview($this->action($arguments), $this->actor, $this->legacyArguments($arguments));
    }
}
