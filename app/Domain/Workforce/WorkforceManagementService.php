<?php

declare(strict_types=1);

namespace App\Domain\Workforce;

use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class WorkforceManagementService
{
    /**
     * Create a shift after applying the normal overlap rule.
     */
    public function createShift(
        User $actor,
        Store $store,
        Worker $worker,
        string $date,
        string $startTime,
        string $endTime,
        bool $allowOverlap,
    ): Shift {
        $this->authorize($actor, $store, $worker);
        $assignments = new ShiftAssignmentService();

        if (!$allowOverlap && $assignments->findOverlaps($actor, $store, $worker, $date, $startTime, $endTime)->isNotEmpty()) {
            Thrower::default()->message('overlap', \__('This shift overlaps an existing assignment.'))->throw();
        }

        return $assignments->create($actor, $store, $worker, $date, $startTime, $endTime);
    }

    /**
     * Create a shift from a preset or return the identical existing shift.
     *
     * @return array{status: 'created'|'exists', shift: Shift}
     */
    public function quickAddShift(
        User $actor,
        Store $store,
        Worker $worker,
        ShiftPreset $preset,
        string $date,
        bool $allowOverlap,
    ): array {
        $this->authorize($actor, $store, $worker);

        if ($preset->getUserId() !== $actor->getKey() || $preset->getStoreId() !== $store->getKey()) {
            \abort(404);
        }

        $assignments = new ShiftAssignmentService();
        $existing = $assignments->findExact(
            $actor,
            $store,
            $worker,
            $date,
            $preset->getStartTimeShort(),
            $preset->getEndTimeShort(),
        );

        if ($existing instanceof Shift) {
            return ['status' => 'exists', 'shift' => $existing];
        }

        if (!$allowOverlap && $assignments->findOverlaps(
            $actor,
            $store,
            $worker,
            $date,
            $preset->getStartTimeShort(),
            $preset->getEndTimeShort(),
        )->isNotEmpty()) {
            Thrower::default()->message('overlap', \__('This shift overlaps an existing assignment.'))->throw();
        }

        return [
            'status' => 'created',
            'shift' => $assignments->create(
                $actor,
                $store,
                $worker,
                $date,
                $preset->getStartTimeShort(),
                $preset->getEndTimeShort(),
            ),
        ];
    }

    /**
     * Update a shift while preserving its worker-rate snapshot semantics.
     */
    public function updateShift(
        User $actor,
        Store $store,
        Shift $shift,
        Worker $worker,
        string $date,
        string $startTime,
        string $endTime,
        bool $allowOverlap,
    ): Shift {
        return DB::transaction(function () use ($actor, $store, $shift, $worker, $date, $startTime, $endTime, $allowOverlap): Shift {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $worker = Typer::assertInstance(
                Worker::query()->whereKey($worker->getKey())->lockForUpdate()->firstOrFail(),
                Worker::class,
            );
            $shift = Typer::assertInstance(
                Shift::query()->whereKey($shift->getKey())->lockForUpdate()->firstOrFail(),
                Shift::class,
            );
            $this->authorize($actor, $store, $worker);

            if ($worker->isArchived() && ($worker->getKey() !== $shift->getWorkerId() || $date >= CarbonImmutable::today()->toDateString())) {
                Thrower::default()->message('worker_id', \__('Archived workers cannot receive new work.'))->throw();
            }

            if ($shift->getUserId() !== $actor->getKey() || $shift->getStoreId() !== $store->getKey()) {
                \abort(404);
            }

            if (!$allowOverlap && (new ShiftAssignmentService())->findOverlaps(
                $actor,
                $store,
                $worker,
                $date,
                $startTime,
                $endTime,
                $shift->getKey(),
            )->isNotEmpty()) {
                Thrower::default()->message('overlap', \__('This shift overlaps an existing assignment.'))->throw();
            }

            $attributes = [
                'worker_id' => $worker->getKey(),
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];

            if ($worker->getKey() !== $shift->getWorkerId()) {
                $attributes['hourly_rate'] = $worker->getHourlyRate();
            }

            $shift->update($attributes);

            return $shift->refresh();
        });
    }

    /**
     * Delete an owned store shift.
     */
    public function deleteShift(User $actor, Store $store, Shift $shift): void
    {
        DB::transaction(function () use ($actor, $store, $shift): void {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $shift = Typer::assertInstance(
                Shift::query()->whereKey($shift->getKey())->lockForUpdate()->firstOrFail(),
                Shift::class,
            );
            $this->authorizeStore($actor, $store);

            if ($shift->getUserId() !== $actor->getKey() || $shift->getStoreId() !== $store->getKey()) {
                \abort(404);
            }

            $shift->delete();
        });
    }

    /**
     * Create a shift preset in an owned store.
     */
    public function createPreset(User $actor, Store $store, string $name, string $startTime, string $endTime): ShiftPreset
    {
        return DB::transaction(function () use ($actor, $store, $name, $startTime, $endTime): ShiftPreset {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $this->authorizeStore($actor, $store);

            return ShiftPreset::query()->create([
                'user_id' => $actor->getKey(),
                'store_id' => $store->getKey(),
                'name' => $name,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
        });
    }

    /**
     * Update an owned store preset.
     */
    public function updatePreset(User $actor, Store $store, ShiftPreset $preset, string $name, string $startTime, string $endTime): ShiftPreset
    {
        return DB::transaction(function () use ($actor, $store, $preset, $name, $startTime, $endTime): ShiftPreset {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $preset = Typer::assertInstance(
                ShiftPreset::query()->whereKey($preset->getKey())->lockForUpdate()->firstOrFail(),
                ShiftPreset::class,
            );
            $this->authorizePreset($actor, $store, $preset);
            $preset->update(['name' => $name, 'start_time' => $startTime, 'end_time' => $endTime]);

            return $preset->refresh();
        });
    }

    /**
     * Delete an owned store preset.
     */
    public function deletePreset(User $actor, Store $store, ShiftPreset $preset): void
    {
        DB::transaction(function () use ($actor, $store, $preset): void {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $preset = Typer::assertInstance(
                ShiftPreset::query()->whereKey($preset->getKey())->lockForUpdate()->firstOrFail(),
                ShiftPreset::class,
            );
            $this->authorizePreset($actor, $store, $preset);
            $preset->delete();
        });
    }

    /**
     * Create a named public shift link with a cryptographically random token.
     */
    public function createShareLink(User $actor, Store $store, string $name): ShiftShareLink
    {
        return DB::transaction(function () use ($actor, $store, $name): ShiftShareLink {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $this->authorizeStore($actor, $store);

            return ShiftShareLink::query()->create([
                'user_id' => $actor->getKey(),
                'store_id' => $store->getKey(),
                'name' => $name,
                'token' => Str::random(64),
            ]);
        });
    }

    /**
     * Revoke an owned public shift link.
     */
    public function deleteShareLink(User $actor, Store $store, ShiftShareLink $link): void
    {
        DB::transaction(function () use ($actor, $store, $link): void {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $link = Typer::assertInstance(
                ShiftShareLink::query()->whereKey($link->getKey())->lockForUpdate()->firstOrFail(),
                ShiftShareLink::class,
            );
            $this->authorizeStore($actor, $store);

            if ($link->getUserId() !== $actor->getKey() || $link->getStoreId() !== $store->getKey()) {
                \abort(404);
            }

            $link->delete();
        });
    }

    /**
     * Ensure the actor owns the store and worker.
     */
    private function authorize(User $actor, Store $store, Worker $worker): void
    {
        $this->authorizeStore($actor, $store);

        if ($worker->getUserId() !== $actor->getKey()) {
            \abort(404);
        }
    }

    /**
     * Ensure the actor is the main administrator who owns the store.
     */
    private function authorizeStore(User $actor, Store $store): void
    {
        if (!$actor->isAdmin() || $store->getUserId() !== $actor->getKey() || !$store->isActive() || $store->isWarehouse()) {
            \abort(404);
        }
    }

    /**
     * Ensure a preset belongs to the selected company store.
     */
    private function authorizePreset(User $actor, Store $store, ShiftPreset $preset): void
    {
        $this->authorizeStore($actor, $store);

        if ($preset->getUserId() !== $actor->getKey() || $preset->getStoreId() !== $store->getKey()) {
            \abort(404);
        }
    }
}
