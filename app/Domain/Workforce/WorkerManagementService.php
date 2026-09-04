<?php

declare(strict_types=1);

namespace App\Domain\Workforce;

use App\Enums\RemovalOutcomeEnum;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

class WorkerManagementService
{
    /**
     * Create a worker record.
     */
    public function createWorker(
        User $actor,
        string $firstName,
        string $lastName,
        float $hourlyRate,
        string|null $calendarColor,
        bool $attendanceRatingEnabled,
    ): Worker {
        $this->assertAdmin($actor);

        return Worker::query()->create([
            'user_id' => $actor->getKey(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'hourly_rate' => $hourlyRate,
            'calendar_color' => Worker::normalizeCalendarColor($calendarColor),
            'attendance_rating_enabled' => $attendanceRatingEnabled,
            'archived_at' => null,
        ]);
    }

    /**
     * Update an owned worker record.
     */
    public function updateWorker(
        User $actor,
        Worker $worker,
        string $firstName,
        string $lastName,
        float $hourlyRate,
        string|null $calendarColor,
        bool $attendanceRatingEnabled,
    ): Worker {
        $this->authorizeWorker($actor, $worker);
        $worker->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'hourly_rate' => $hourlyRate,
            'calendar_color' => Worker::normalizeCalendarColor($calendarColor),
            'attendance_rating_enabled' => $attendanceRatingEnabled,
        ]);

        return $worker->refresh();
    }

    /**
     * Delete a pristine worker, archive a historical worker, or block live work.
     */
    public function deleteWorker(User $actor, Worker $worker): RemovalOutcomeEnum
    {
        $this->authorizeWorker($actor, $worker);

        return DB::transaction(function () use ($actor, $worker): RemovalOutcomeEnum {
            $lockedWorker = Typer::assertInstance(Worker::query()->lockForUpdate()->findOrFail($worker->getKey()), Worker::class);
            $this->authorizeWorker($actor, $lockedWorker);

            if ($lockedWorker->isArchived()) {
                return RemovalOutcomeEnum::ARCHIVED;
            }

            if ($this->workerHasLiveWork($lockedWorker)) {
                return RemovalOutcomeEnum::BLOCKED;
            }

            if ($this->workerHasHistory($lockedWorker)) {
                $lockedWorker->update(['archived_at' => CarbonImmutable::now()]);

                return RemovalOutcomeEnum::ARCHIVED;
            }

            $lockedWorker->delete();

            return RemovalOutcomeEnum::DELETED;
        });
    }

    /**
     * Return an archived worker to prospective work selectors.
     */
    public function restoreWorker(User $actor, Worker $worker): Worker
    {
        $this->authorizeWorker($actor, $worker);

        return DB::transaction(function () use ($actor, $worker): Worker {
            $lockedWorker = Typer::assertInstance(Worker::query()->lockForUpdate()->findOrFail($worker->getKey()), Worker::class);
            $this->authorizeWorker($actor, $lockedWorker);
            $lockedWorker->update(['archived_at' => null]);

            return $lockedWorker->refresh();
        });
    }

    /**
     * Ensure a worker belongs to the main administrator.
     */
    private function authorizeWorker(User $actor, Worker $worker): void
    {
        $this->assertAdmin($actor);

        if ($worker->getUserId() !== $actor->getKey()) {
            \abort(404);
        }
    }

    /**
     * Ensure the assistant actor is the main administrator.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
    }

    /**
     * Live attendance and future scheduling must be resolved before archival.
     */
    private function workerHasLiveWork(Worker $worker): bool
    {
        $workerId = $worker->getKey();
        $today = CarbonImmutable::today()->toDateString();

        if (DB::table('attendance_sessions')->where('worker_id', $workerId)->whereNull('ended_at')->whereNull('voided_at')->exists()) {
            return true;
        }

        if (DB::table('attendance_sessions')->where('active_worker_id', $workerId)->whereNull('ended_at')->whereNull('voided_at')->exists()) {
            return true;
        }

        if (DB::table('shifts')->where('worker_id', $workerId)->whereDate('date', '>=', $today)->exists()) {
            return true;
        }

        return DB::table('shift_requests')->where('worker_id', $workerId)->whereDate('date', '>=', $today)->exists();
    }

    /**
     * Any historical reference keeps the worker row and its identity intact.
     */
    private function workerHasHistory(Worker $worker): bool
    {
        $workerId = $worker->getKey();
        $references = [
            ['shifts', 'worker_id'],
            ['shift_requests', 'worker_id'],
            ['attendance_sessions', 'worker_id'],
            ['attendance_sessions', 'active_worker_id'],
            ['payroll_adjustments', 'worker_id'],
            ['payroll_wage_overrides', 'worker_id'],
            ['payroll_worker_entries', 'worker_id'],
            ['checklist_items', 'completed_by_worker_id'],
            ['checklist_events', 'worker_id'],
            ['recipe_test_attempts', 'worker_id'],
            ['recipe_test_sessions', 'worker_id'],
        ];

        foreach ($references as [$table, $column]) {
            if (DB::table($table)->where($column, $workerId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
