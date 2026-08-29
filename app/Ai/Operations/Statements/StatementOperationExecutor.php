<?php

declare(strict_types=1);

namespace App\Ai\Operations\Statements;

use App\Ai\Operations\AssistantOperationExecutor;
use App\Models\Statement;
use App\Models\User;
use App\Operations\Statements\ManageStatements;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class StatementOperationExecutor implements AssistantOperationExecutor
{
    /**
     * Create the assistant adapter around the shared statement command.
     */
    public function __construct(
        private readonly ManageStatements $command,
    ) {}

    /**
     * Validate ownership, business values, and expected effects.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function preview(string $identifier, User $actor, array $arguments): array
    {
        $targetId = Typer::assertInt(Typer::parseNullableInt($arguments['target_id'] ?? null));
        $values = $this->values($arguments);
        $statement = match ($identifier) {
            'restore_statement_version' => $this->command->version($actor, $targetId)->getStatement(),
            'update_statement', 'update_today_statement', 'clear_statement' => $this->command->statement($actor, $targetId),
            default => throw new InvalidArgumentException('Unknown statement operation.'),
        };

        if ($identifier === 'update_statement') {
            $this->command->validateUpdate($statement, $values);
        } elseif ($identifier === 'update_today_statement') {
            $this->command->validateToday($statement, $values);
        }

        $this->assertStore($arguments, $statement);

        return [
            'operation' => $identifier,
            'store' => ['id' => $statement->getStoreId(), 'name' => $statement->getStore()->getName()],
            'target' => [
                'type' => $identifier === 'restore_statement_version' ? 'statement_version' : 'statement',
                'id' => (string) $targetId,
            ],
            'effects' => $this->effects($identifier),
            'sanitized_arguments' => ['values' => $values],
            'safe_editable_fields' => ['values_json'],
        ];
    }

    /**
     * Execute an approved statement operation.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(string $identifier, User $actor, array $arguments): array
    {
        $targetId = Typer::assertInt(Typer::parseNullableInt($arguments['target_id'] ?? null));
        $values = $this->values($arguments);
        $statement = match ($identifier) {
            'update_statement' => $this->command->update($actor, $targetId, $values),
            'update_today_statement' => $this->command->updateToday($actor, $targetId, $values),
            'clear_statement' => $this->command->clear($actor, $targetId),
            'restore_statement_version' => $this->command->restoreVersion($actor, $targetId),
            default => throw new InvalidArgumentException('Unknown statement operation.'),
        };

        return [
            'operation' => $identifier,
            'status' => 'succeeded',
            'record' => [
                'type' => 'statement',
                'id' => $statement->getKey(),
                'store_id' => $statement->getStoreId(),
                'year' => $statement->getYear(),
                'month' => $statement->getMonth(),
                'url' => Resolver::resolveUrlGenerator()->route('statements.index', [
                    'store_id' => $statement->getStoreId(),
                    'year' => $statement->getYear(),
                    'month' => $statement->getMonth(),
                ]),
            ],
        ];
    }

    /**
     * Decode the strictly bounded editable JSON values.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function values(array $arguments): array
    {
        return Typer::assertStringKeyArray(Typer::assertArray(\json_decode(
            Typer::assertString($arguments['values_json'] ?? null),
            true,
            32,
            \JSON_THROW_ON_ERROR,
        )));
    }

    /**
     * Ensure the model-proposed store matches the resolved statement store.
     *
     * @param array<string, mixed> $arguments
     */
    private function assertStore(array $arguments, Statement $statement): void
    {
        if (Typer::parseNullableInt($arguments['store_id'] ?? null) !== $statement->getStoreId()) {
            throw new InvalidArgumentException('The resolved statement store is locked.');
        }
    }

    /**
     * Describe the normal statement effects shown before approval.
     *
     * @return list<string>
     */
    private function effects(string $identifier): array
    {
        return match ($identifier) {
            'update_statement' => ['Snapshots the current statement.', 'Updates the selected daily values and optionally closes current attendances.'],
            'update_today_statement' => ['Snapshots the current statement.', 'Updates today’s Prague-local values and optionally closes current attendances.'],
            'clear_statement' => ['Snapshots the current statement.', 'Clears all daily values through the normal statement service.'],
            'restore_statement_version' => ['Snapshots the current state before restore.', 'Restores every daily value from the selected version.'],
            default => throw new InvalidArgumentException('Unknown statement operation.'),
        };
    }
}
