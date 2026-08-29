<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\AssistantActionPresenter;
use App\Http\Validation\WorkerValidity;
use App\Models\Worker;
use App\Services\AdministrationManagementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class WriteWorkersTool extends AbstractApprovableResourceTool
{
    /**
     * Return the stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'write_workers';
    }

    /**
     * Describe worker lifecycle mutations to the model.
     */
    public function description(): string
    {
        return 'Create, update, or delete workers. Only first name, last name, and hourly rate are required when creating or updating; attendance rating and calendar color are optional.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $values = fn(): ObjectType => $schema->object([
            'first_name' => $schema->string()->required(),
            'last_name' => $schema->string()->required(),
            'hourly_rate' => $schema->number()->min(0)->required(),
            'attendance_rating_enabled' => $schema->boolean(),
            'calendar_color' => $schema->string()->description('Optional full hexadecimal color such as #10B981.'),
        ])->withoutAdditionalProperties();

        return [
            'request' => $schema->anyOf([
                $schema->object([
                    'action' => $schema->string()->enum(['create_worker'])->required(),
                    'values' => $values()->required(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'action' => $schema->string()->enum(['update_worker'])->required(),
                    'target_id' => $schema->integer()->required(),
                    'values' => $values()->required(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'action' => $schema->string()->enum(['delete_worker'])->required(),
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
        return 'workers';
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<string>
     */
    public function safeEditablePaths(array $arguments): array
    {
        return $this->action($arguments) === 'delete_worker' ? [] : [
            'request.values.first_name',
            'request.values.last_name',
            'request.values.hourly_rate',
            'request.values.attendance_rating_enabled',
            'request.values.calendar_color',
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{store_id: null, store_name: null, target_type: string|null, target_id: string|null}
     */
    public function auditContext(array $arguments): array
    {
        $target = $this->target($arguments);

        return [
            'store_id' => null,
            'store_name' => null,
            'target_type' => $target instanceof Worker ? 'worker' : null,
            'target_id' => $target instanceof Worker ? (string) $target->getKey() : null,
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function preview(array $arguments): array
    {
        $this->assertAdmin();
        $action = $this->action($arguments);
        $target = $this->target($arguments);
        $values = $this->values($arguments);

        if ($action !== 'delete_worker') {
            $this->validate($values);
        }

        return Resolver::resolve(AssistantActionPresenter::class)->present($arguments, [
            'store' => null,
            'target' => $target instanceof Worker ? ['type' => 'worker', 'id' => (string) $target->getKey()] : null,
            'summary_params' => $target instanceof Worker ? [
                'first_name' => $target->getFirstName(),
                'last_name' => $target->getLastName(),
            ] : [],
        ]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function execute(array $arguments): array
    {
        $this->assertAdmin();
        $action = $this->action($arguments);
        $target = $this->target($arguments);
        $values = $this->values($arguments);
        $service = Resolver::resolve(AdministrationManagementService::class);

        if ($action === 'create_worker' || $action === 'update_worker') {
            $this->validate($values);
            $worker = $action === 'create_worker'
                ? $service->createWorker(
                    $this->actor,
                    Typer::assertString($values['first_name'] ?? null),
                    Typer::assertString($values['last_name'] ?? null),
                    Typer::parseFloat($values['hourly_rate'] ?? null),
                    Typer::parseNullableString($values['calendar_color'] ?? null),
                    Typer::parseBool($values['attendance_rating_enabled'] ?? true),
                )
                : $service->updateWorker(
                    $this->actor,
                    Typer::assertInstance($target, Worker::class),
                    Typer::assertString($values['first_name'] ?? null),
                    Typer::assertString($values['last_name'] ?? null),
                    Typer::parseFloat($values['hourly_rate'] ?? null),
                    Typer::parseNullableString($values['calendar_color'] ?? null),
                    Typer::parseBool($values['attendance_rating_enabled'] ?? $target?->isAttendanceRatingEnabled()),
                );

            return $this->result($action, $worker->getKey());
        }

        $worker = Typer::assertInstance($target, Worker::class);

        if (!$service->deleteWorker($this->actor, $worker)) {
            throw new RuntimeException('Cannot delete a worker with existing shifts.');
        }

        return $this->result($action, $worker->getKey());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function target(array $arguments): Worker|null
    {
        $action = $this->action($arguments);

        if ($action === 'create_worker') {
            return null;
        }

        if (!\in_array($action, ['update_worker', 'delete_worker'], true)) {
            throw new InvalidArgumentException('Unknown worker action.');
        }

        return Typer::assertInstance(Worker::query()
            ->where('user_id', $this->actor->getKey())
            ->whereKey(Typer::parseInt($this->request($arguments)['target_id'] ?? null))
            ->firstOrFail(), Worker::class);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function validate(array $values): void
    {
        $validity = WorkerValidity::inject($this->actor->getKey());

        Resolver::resolveValidatorFactory()->make($values, [
            'first_name' => $validity->firstName()->required()->toArray(),
            'last_name' => $validity->lastName()->required()->toArray(),
            'hourly_rate' => $validity->hourlyRate()->required()->toArray(),
            'attendance_rating_enabled' => $validity->attendanceRatingEnabled()->nullable()->toArray(),
            'calendar_color' => $validity->calendarColor()->nullable()->toArray(),
        ])->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private function result(string $action, int $recordId): array
    {
        return [
            'operation' => $action,
            'status' => 'succeeded',
            'record' => [
                'type' => 'worker',
                'id' => $recordId,
                'store_id' => null,
                'url' => Resolver::resolveUrlGenerator()->route('workers.index'),
            ],
        ];
    }

    /**
     * Require the bound actor to remain the main administrator.
     */
    private function assertAdmin(): void
    {
        if (!$this->actor->isAdmin()) {
            \abort(403);
        }
    }
}
