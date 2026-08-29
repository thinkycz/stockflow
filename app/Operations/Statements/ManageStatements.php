<?php

declare(strict_types=1);

namespace App\Operations\Statements;

use App\Http\Validation\StatementValidity;
use App\Models\Statement;
use App\Models\StatementVersion;
use App\Models\User;
use App\Services\StatementService;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ManageStatements
{
    /**
     * Create the shared statement command.
     */
    public function __construct(
        private readonly StatementService $service,
    ) {}

    /**
     * Update a statement's day rows through the normal snapshot pipeline.
     *
     * @param array<string, mixed> $payload
     */
    public function update(User $actor, int $statementId, array $payload): Statement
    {
        $statement = $this->statement($actor, $statementId);
        $validated = $this->validateUpdate($statement, $payload);
        $rows = $this->rows($validated['days'] ?? null);

        if (Typer::parseBool($validated['close_attendances'] ?? false)) {
            $now = Carbon::now(StatementService::TIMEZONE);

            if ($now->year !== $statement->getYear() || $now->month !== $statement->getMonth()) {
                \abort(403);
            }

            $this->service->updateDaysAndCloseAttendances($statement, $rows, $actor);
        } else {
            $this->service->updateDays($statement, $rows, $actor);
        }

        return $statement->refresh();
    }

    /**
     * Update the current Prague business day only.
     *
     * @param array<string, mixed> $payload
     */
    public function updateToday(User $actor, int $statementId, array $payload): Statement
    {
        $statement = $this->statement($actor, $statementId);
        $today = Carbon::now(StatementService::TIMEZONE);

        if ($today->year !== $statement->getYear() || $today->month !== $statement->getMonth()) {
            \abort(404);
        }

        $validated = $this->validateToday($statement, $payload);
        $rows = [[
            'date' => $today->toDateString(),
            'cash' => Typer::parseFloat($validated['cash'] ?? null),
            'card' => Typer::parseFloat($validated['card'] ?? null),
            'wolt' => Typer::parseFloat($validated['wolt'] ?? null),
            'bolt' => Typer::parseFloat($validated['bolt'] ?? null),
            'bolt_cash' => Typer::parseFloat($validated['bolt_cash'] ?? null),
            'foodora' => Typer::parseFloat($validated['foodora'] ?? null),
        ]];

        if (Typer::parseBool($validated['close_attendances'] ?? false)) {
            $this->service->updateDaysAndCloseAttendances($statement, $rows, $actor);
        } else {
            $this->service->updateDays($statement, $rows, $actor);
        }

        return $statement->refresh();
    }

    /**
     * Clear a statement through its normal snapshot and activity pipeline.
     */
    public function clear(User $actor, int $statementId): Statement
    {
        $statement = $this->statement($actor, $statementId);
        $this->service->clear($statement, $actor);

        return $statement->refresh();
    }

    /**
     * Restore a statement version while preserving the current backup snapshot.
     */
    public function restoreVersion(User $actor, int $versionId): Statement
    {
        $version = $this->version($actor, $versionId);
        $this->service->restoreVersion($version, $actor);

        return $version->getStatement()->refresh();
    }

    /**
     * Resolve and authorize one owned statement.
     */
    public function statement(User $actor, int $statementId): Statement
    {
        $statement = Statement::query()
            ->where('user_id', $actor->resolveScopeUser()->getKey())
            ->whereKey($statementId)
            ->first();

        if (!$statement instanceof Statement) {
            \abort(404);
        }

        $this->ensureStoreAccess($actor, $statement);

        return $statement;
    }

    /**
     * Resolve and authorize one owned statement version.
     */
    public function version(User $actor, int $versionId): StatementVersion
    {
        $version = StatementVersion::query()
            ->where('user_id', $actor->resolveScopeUser()->getKey())
            ->whereKey($versionId)
            ->first();

        if (!$version instanceof StatementVersion) {
            \abort(404);
        }

        $this->ensureStoreAccess($actor, $version->getStatement());

        return $version;
    }

    /**
     * Validate a full statement update without side effects.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function validateUpdate(Statement $statement, array $payload): array
    {
        $validity = StatementValidity::inject($statement->getUserId());

        return Typer::assertStringKeyArray(Resolver::resolveValidatorFactory()->make($payload, [
            'days' => $validity->days()->required()->toArray(),
            'days.*.date' => $validity->dayDate()->required()->toArray(),
            'days.*.cash' => $validity->amount()->required()->toArray(),
            'days.*.card' => $validity->amount()->required()->toArray(),
            'days.*.wolt' => $validity->amount()->required()->toArray(),
            'days.*.bolt' => $validity->amount()->required()->toArray(),
            'days.*.bolt_cash' => $validity->amount()->required()->toArray(),
            'days.*.foodora' => $validity->amount()->required()->toArray(),
            'days.*.cash_checked' => $validity->cashChecked()->sometimes()->nullable()->toArray(),
            'close_attendances' => $validity->closeAttendances()->sometimes()->nullable()->toArray(),
        ])->validate());
    }

    /**
     * Validate a current-day statement update without side effects.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function validateToday(Statement $statement, array $payload): array
    {
        $validity = StatementValidity::inject($statement->getUserId());

        return Typer::assertStringKeyArray(Resolver::resolveValidatorFactory()->make($payload, [
            'cash' => $validity->amount()->required()->toArray(),
            'card' => $validity->amount()->required()->toArray(),
            'wolt' => $validity->amount()->required()->toArray(),
            'bolt' => $validity->amount()->required()->toArray(),
            'bolt_cash' => $validity->amount()->required()->toArray(),
            'foodora' => $validity->amount()->required()->toArray(),
            'close_attendances' => $validity->closeAttendances()->sometimes()->nullable()->toArray(),
        ])->validate());
    }

    /**
     * Ensure a limited user is confined to their assigned store.
     */
    private function ensureStoreAccess(User $actor, Statement $statement): void
    {
        if (!$actor->isAdmin() && $actor->getAssignedStoreId() !== $statement->getStoreId()) {
            \abort(403);
        }
    }

    /**
     * Normalize validated day rows into the statement service contract.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $value): array
    {
        $rows = [];

        foreach (Typer::assertArray($value) as $row) {
            $rows[] = Typer::assertStringKeyArray(Typer::assertArray($row));
        }

        return $rows;
    }
}
