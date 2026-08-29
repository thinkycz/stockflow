<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\AssistantResourceToolDefinitions;
use App\Ai\Operations\AssistantOperationExecutor;
use App\Enums\AssistantActionClassificationEnum;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\IntegerType;
use Illuminate\JsonSchema\Types\NumberType;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Typer;

abstract class ConfiguredWriteResourceTool extends AbstractExecutorBackedResourceTool
{
    /**
     * Provider-facing name supplied by each final resource tool.
     */
    protected const string TOOL_NAME = '';

    /**
     * Stable provider-facing tool name.
     */
    private readonly string $toolName;

    /**
     * Human-service adapter for this resource tool.
     */
    private readonly AssistantOperationExecutor $actionExecutor;

    /**
     * Audit domain recorded for this resource tool.
     */
    private readonly string $toolDomain;

    /**
     * Model-facing description of this resource tool.
     */
    private readonly string $toolDescription;

    /**
     * @var array<string, array<string, mixed>>
     */
    private readonly array $actions;

    /**
     * @var list<string>
     */
    private readonly array $externalActions;

    /**
     * Construct one explicitly named native resource writer.
     */
    public function __construct(
        User $actor,
        string $conversationId,
        AssistantOperationExecutor $actionExecutor,
    ) {
        $toolName = static::TOOL_NAME;

        if ($toolName === '') {
            throw new InvalidArgumentException('Concrete resource writers must declare a tool name.');
        }

        $definition = AssistantResourceToolDefinitions::writers()[$toolName] ?? throw new InvalidArgumentException('Unknown resource writer.');
        $this->toolName = $toolName;
        $this->actionExecutor = $actionExecutor;
        $this->toolDomain = $definition['domain'];
        $this->toolDescription = $definition['description'];
        $this->actions = $definition['actions'];
        $this->externalActions = $definition['external_actions'];
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
     * Describe the resource writer to the model.
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
        $branches = [];

        foreach ($this->actions as $action => $definition) {
            $properties = [
                'action' => $schema->string()->enum([$action])->required(),
            ];

            if (($definition['store'] ?? false) === true) {
                $properties['store_id'] = $schema->integer()->description('Locked owned store ID.')->required();
            }

            if (($definition['target'] ?? false) === true) {
                $properties['target_id'] = $schema->integer()->description('Locked target record ID.')->required();
            }

            $context = $this->fields($schema, $this->fieldDefinitions($definition, 'context'));
            $values = $this->fields($schema, $this->fieldDefinitions($definition, 'values'));

            if ($context !== []) {
                $properties['context'] = $schema->object($context)->withoutAdditionalProperties()->required();
            }

            if ($values !== []) {
                $properties['values'] = $schema->object($values)->withoutAdditionalProperties()->required();
            }

            $branches[] = $schema->object($properties)->withoutAdditionalProperties();
        }

        return ['request' => $schema->anyOf($branches)->required()];
    }

    /**
     * Return the fixed audit domain.
     */
    public function auditDomain(): string
    {
        return $this->toolDomain;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function auditClassification(array $arguments): AssistantActionClassificationEnum
    {
        return \in_array($this->action($arguments), $this->externalActions, true)
            ? AssistantActionClassificationEnum::EXTERNAL_SIDE_EFFECT
            : AssistantActionClassificationEnum::MUTATION;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<string>
     */
    public function safeEditablePaths(array $arguments): array
    {
        return $this->editablePaths(
            $this->fieldDefinitions($this->definition($arguments), 'values'),
            'request.values',
        );
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array<string, mixed>>
     */
    protected function editableFields(array $arguments): array
    {
        $fields = [];

        foreach ($this->fieldDefinitions($this->definition($arguments), 'values') as $name => $definition) {
            if (($definition['editable'] ?? true) !== true) {
                continue;
            }

            $fields[] = [
                'path' => 'request.values.' . $name,
                'label' => $definition['label'] ?? \str_replace('_', ' ', \ucfirst($name)),
                'control' => $definition['control'] ?? $this->control($definition),
                'required' => ($definition['required'] ?? false) === true,
                ...\array_filter([
                    'min' => $definition['min'] ?? null,
                    'max' => $definition['max'] ?? null,
                    'step' => $definition['step'] ?? null,
                    'options' => $definition['options'] ?? null,
                    'fields' => $definition['fields'] ?? null,
                ], static fn(mixed $value): bool => $value !== null),
            ];
        }

        return $fields;
    }

    /**
     * Return the human-service adapter for this concrete resource writer.
     */
    protected function executor(): AssistantOperationExecutor
    {
        return $this->actionExecutor;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function definition(array $arguments): array
    {
        return $this->actions[$this->action($arguments)] ?? throw new InvalidArgumentException('Unknown resource action.');
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, array<string, mixed>>
     */
    private function fieldDefinitions(array $definition, string $key): array
    {
        $fields = $definition[$key] ?? [];

        if (!\is_array($fields)) {
            throw new InvalidArgumentException('Tool field definitions must be arrays.');
        }

        $definitions = [];

        foreach ($fields as $name => $field) {
            if (!\is_string($name) || !\is_array($field)) {
                throw new InvalidArgumentException('Each tool field definition must be a named object.');
            }

            $definitions[$name] = Typer::assertStringKeyArray($field);
        }

        return $definitions;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, Type>
     */
    private function fields(JsonSchema $schema, array $definitions): array
    {
        $fields = [];

        foreach ($definitions as $name => $definition) {
            $type = $this->type($schema, $definition);

            if (($definition['required'] ?? false) === true) {
                $type->required();
            }

            $fields[$name] = $type;
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function type(JsonSchema $schema, array $definition): Type
    {
        $type = match ($definition['type'] ?? 'string') {
            'boolean' => $schema->boolean(),
            'integer' => $schema->integer(),
            'number' => $schema->number(),
            'array' => $this->arrayType($schema, $definition),
            'object' => $schema->object($this->fields($schema, $this->fieldDefinitions($definition, 'fields')))->withoutAdditionalProperties(),
            default => $schema->string(),
        };

        if (\is_array($definition['enum'] ?? null)) {
            $type->enum(\array_values($definition['enum']));
        }

        if ((\is_int($definition['min'] ?? null) || \is_float($definition['min'] ?? null)) &&
            ($type instanceof IntegerType || $type instanceof NumberType || $type instanceof StringType)) {
            $type->min($definition['min']);
        }

        if ((\is_int($definition['max'] ?? null) || \is_float($definition['max'] ?? null)) &&
            ($type instanceof IntegerType || $type instanceof NumberType || $type instanceof StringType)) {
            $type->max($definition['max']);
        }

        if (\is_string($definition['description'] ?? null)) {
            $type->description($definition['description']);
        }

        return $type;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function arrayType(JsonSchema $schema, array $definition): ArrayType
    {
        $item = $definition['items'] ?? ['type' => 'string'];

        if (!\is_array($item)) {
            throw new InvalidArgumentException('Array item definitions must be arrays.');
        }

        $array = $schema->array()->items($this->type($schema, \array_filter(
            $item,
            static fn(mixed $value, int|string $key): bool => \is_string($key),
            \ARRAY_FILTER_USE_BOTH,
        )));

        if (\is_int($definition['min_items'] ?? null)) {
            $array->min($definition['min_items']);
        }

        if (\is_int($definition['max_items'] ?? null)) {
            $array->max($definition['max_items']);
        }

        return $array;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return list<string>
     */
    private function editablePaths(array $definitions, string $prefix): array
    {
        $paths = [];

        foreach ($definitions as $name => $definition) {
            if (($definition['editable'] ?? true) !== true) {
                continue;
            }

            $path = $prefix . '.' . $name;

            $items = $definition['items'] ?? null;

            if (($definition['type'] ?? null) === 'array' && \is_array($items) && ($items['type'] ?? null) === 'object') {
                $paths = [...$paths, ...$this->editablePaths($this->fieldDefinitions(
                    \array_filter($items, static fn(mixed $value, int|string $key): bool => \is_string($key), \ARRAY_FILTER_USE_BOTH),
                    'fields',
                ), $path . '.*')];
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function control(array $definition): string
    {
        return match ($definition['type'] ?? 'string') {
            'boolean' => 'checkbox',
            'integer', 'number' => 'number',
            'array' => 'collection',
            default => \is_array($definition['enum'] ?? null) ? 'select' : 'text',
        };
    }
}
