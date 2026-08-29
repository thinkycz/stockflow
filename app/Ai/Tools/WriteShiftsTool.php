<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Operations\AssistantOperationExecutor;
use App\Ai\Operations\Workforce\WorkforceOperationExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;
use Thinkycz\LaravelCore\Support\Resolver;

final class WriteShiftsTool extends AbstractExecutorBackedResourceTool
{
    /**
     * Return the stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'write_shifts';
    }

    /**
     * Describe shift and preset lifecycle mutations to the model.
     */
    public function description(): string
    {
        return 'Create, quick-add, update, or delete scheduled shifts and manage reusable shift presets. Every valid action requires individual approval.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $shiftValues = fn(): ObjectType => $schema->object([
            'date' => $schema->string()->description('Shift date in YYYY-MM-DD format.')->required(),
            'start_time' => $schema->string()->description('Start time in HH:MM format.')->required(),
            'end_time' => $schema->string()->description('End time in HH:MM format.')->required(),
            'allow_overlap' => $schema->boolean(),
        ])->withoutAdditionalProperties();
        $presetValues = fn(): ObjectType => $schema->object([
            'name' => $schema->string()->required(),
            'start_time' => $schema->string()->required(),
            'end_time' => $schema->string()->required(),
        ])->withoutAdditionalProperties();
        $workerContext = fn(): ObjectType => $schema->object([
            'worker_id' => $schema->integer()->required(),
        ])->withoutAdditionalProperties();

        return [
            'request' => $schema->anyOf([
                $schema->object([
                    'action' => $schema->string()->enum(['create_shift'])->required(),
                    'store_id' => $schema->integer()->required(),
                    'context' => $workerContext()->required(),
                    'values' => $shiftValues()->required(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'action' => $schema->string()->enum(['quick_add_shift'])->required(),
                    'store_id' => $schema->integer()->required(),
                    'target_id' => $schema->integer()->description('Locked shift preset ID.')->required(),
                    'context' => $workerContext()->required(),
                    'values' => $schema->object([
                        'date' => $schema->string()->required(),
                        'allow_overlap' => $schema->boolean(),
                    ])->withoutAdditionalProperties()->required(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'action' => $schema->string()->enum(['update_shift'])->required(),
                    'store_id' => $schema->integer()->required(),
                    'target_id' => $schema->integer()->description('Locked shift ID.')->required(),
                    'context' => $workerContext()->required(),
                    'values' => $shiftValues()->required(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'action' => $schema->string()->enum(['delete_shift'])->required(),
                    'store_id' => $schema->integer()->required(),
                    'target_id' => $schema->integer()->required(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'action' => $schema->string()->enum(['create_shift_preset'])->required(),
                    'store_id' => $schema->integer()->required(),
                    'values' => $presetValues()->required(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'action' => $schema->string()->enum(['update_shift_preset'])->required(),
                    'store_id' => $schema->integer()->required(),
                    'target_id' => $schema->integer()->required(),
                    'values' => $presetValues()->required(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'action' => $schema->string()->enum(['delete_shift_preset'])->required(),
                    'store_id' => $schema->integer()->required(),
                    'target_id' => $schema->integer()->required(),
                ])->withoutAdditionalProperties(),
            ])->required(),
        ];
    }

    /**
     * Return the workforce audit domain.
     */
    public function auditDomain(): string
    {
        return 'shifts';
    }

    /**
     * @param array<string, mixed> $arguments @return list<string>
     */
    public function safeEditablePaths(array $arguments): array
    {
        return match ($this->action($arguments)) {
            'create_shift', 'update_shift' => [
                'request.values.date',
                'request.values.start_time',
                'request.values.end_time',
                'request.values.allow_overlap',
            ],
            'quick_add_shift' => ['request.values.date', 'request.values.allow_overlap'],
            'create_shift_preset', 'update_shift_preset' => [
                'request.values.name',
                'request.values.start_time',
                'request.values.end_time',
            ],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array<string, mixed>>
     */
    protected function editableFields(array $arguments): array
    {
        return match ($this->action($arguments)) {
            'create_shift', 'update_shift' => [
                ['path' => 'request.values.date', 'label' => 'Date', 'control' => 'date', 'required' => true],
                ['path' => 'request.values.start_time', 'label' => 'Start time', 'control' => 'time', 'required' => true],
                ['path' => 'request.values.end_time', 'label' => 'End time', 'control' => 'time', 'required' => true],
                ['path' => 'request.values.allow_overlap', 'label' => 'Allow overlap', 'control' => 'checkbox', 'required' => false],
            ],
            'quick_add_shift' => [
                ['path' => 'request.values.date', 'label' => 'Date', 'control' => 'date', 'required' => true],
                ['path' => 'request.values.allow_overlap', 'label' => 'Allow overlap', 'control' => 'checkbox', 'required' => false],
            ],
            'create_shift_preset', 'update_shift_preset' => [
                ['path' => 'request.values.name', 'label' => 'Name', 'control' => 'text', 'required' => true],
                ['path' => 'request.values.start_time', 'label' => 'Start time', 'control' => 'time', 'required' => true],
                ['path' => 'request.values.end_time', 'label' => 'End time', 'control' => 'time', 'required' => true],
            ],
            default => [],
        };
    }

    /**
     * Return the human-service adapter for shift mutations.
     */
    protected function executor(): AssistantOperationExecutor
    {
        return Resolver::resolve(WorkforceOperationExecutor::class);
    }
}
