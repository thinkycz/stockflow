<?php

declare(strict_types=1);

namespace App\Domain\Workforce;

use App\Models\Shift;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftRequestService
{
    /**
     * Convert a request into a shift, optionally overriding an overlap.
     */
    public function approve(
        User $admin,
        Store $store,
        int $shiftRequestId,
        string $startTime,
        string $endTime,
        bool $allowOverlap,
    ): Shift {
        $this->assertAdminStore($admin, $store);

        return DB::transaction(function () use ($admin, $store, $shiftRequestId, $startTime, $endTime, $allowOverlap): Shift {
            $lockedStore = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $this->assertAdminStore($admin, $lockedStore);
            $shiftRequestQuery = ShiftRequest::query()
                ->whereKey($shiftRequestId)
                ->where('user_id', $admin->getKey())
                ->where('store_id', $lockedStore->getKey());
            $shiftRequest = Typer::assertInstance(
                $shiftRequestQuery->lockForUpdate()->firstOrFail(),
                ShiftRequest::class,
            );
            $worker = Typer::assertInstance(
                Worker::query()
                    ->whereKey($shiftRequest->getWorkerId())
                    ->where('user_id', $admin->getKey())
                    ->lockForUpdate()
                    ->firstOrFail(),
                Worker::class,
            );
            $this->assertWorker($lockedStore, $worker);
            $assignmentService = new ShiftAssignmentService();
            $existingShift = $assignmentService->findExact(
                $admin,
                $lockedStore,
                $worker,
                $shiftRequest->getDate(),
                $startTime,
                $endTime,
            );

            if ($existingShift instanceof Shift) {
                $shiftRequest->delete();

                return $existingShift;
            }

            if (!$allowOverlap && $assignmentService->findOverlaps(
                $admin,
                $lockedStore,
                $worker,
                $shiftRequest->getDate(),
                $startTime,
                $endTime,
            )->isNotEmpty()) {
                Thrower::default()->message('overlap', \__('This shift overlaps an existing assignment.'))->throw();
            }

            $shift = $assignmentService->create(
                $admin,
                $lockedStore,
                $worker,
                $shiftRequest->getDate(),
                $startTime,
                $endTime,
            );
            $shiftRequest->delete();

            return $shift;
        });
    }

    /**
     * Toggle one worker's request for a day.
     *
     * @return array{status: 'created'|'deleted'|'updated', request: ShiftRequest|null}
     */
    public function toggle(Store $store, Worker $worker, string $date, string $startTime, string $endTime): array
    {
        return DB::transaction(function () use ($store, $worker, $date, $startTime, $endTime): array {
            $lockedStore = Typer::assertInstance(Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(), Store::class);
            if (!$lockedStore->isActive() || $lockedStore->isWarehouse()) {
                Thrower::default()->message('store_id', \__('The selected store is invalid.'))->throw();
            }
            $worker = Typer::assertInstance(
                Worker::query()->whereKey($worker->getKey())->lockForUpdate()->firstOrFail(),
                Worker::class,
            );
            $this->assertWorker($lockedStore, $worker);
            $period = CarbonImmutable::parse($date)->startOfMonth();
            $this->assertFuturePeriod($period->year, $period->month);

            if ($this->isLocked($lockedStore, $period->year, $period->month)) {
                Thrower::default()->message('date', \__('Shift requests for this month are locked.'))->throw();
            }

            $query = ShiftRequest::query();
            ShiftRequest::scopeForStore($query, $lockedStore->getKey());
            ShiftRequest::scopeForWorker($query, $worker->getKey());
            $request = $query->whereDate('date', $date)->lockForUpdate()->first();

            if ($request instanceof ShiftRequest) {
                if ($startTime === $request->getStartTimeShort() && $endTime === $request->getEndTimeShort()) {
                    $request->delete();

                    return ['status' => 'deleted', 'request' => null];
                }

                $request->update(['start_time' => $startTime, 'end_time' => $endTime]);

                return ['status' => 'updated', 'request' => $request->refresh()];
            }

            $request = ShiftRequest::query()->create([
                'user_id' => $lockedStore->getUserId(),
                'store_id' => $lockedStore->getKey(),
                'worker_id' => $worker->getKey(),
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

            return ['status' => 'created', 'request' => $request];
        });
    }

    /**
     * Set the reversible request lock for one future store month.
     */
    public function setLocked(User $admin, Store $store, int $year, int $month, bool $locked): void
    {
        $this->assertAdminStore($admin, $store);
        $this->assertFuturePeriod($year, $month);

        DB::transaction(function () use ($admin, $store, $year, $month, $locked): void {
            $lockedStore = Typer::assertInstance(Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(), Store::class);
            $this->assertAdminStore($admin, $lockedStore);

            if ($locked) {
                ShiftRequestMonthLock::query()->updateOrCreate(
                    ['store_id' => $lockedStore->getKey(), 'year' => $year, 'month' => $month],
                    [
                        'user_id' => $admin->getKey(),
                        'locked_at' => CarbonImmutable::now(),
                        'locked_by_user_id' => $admin->getKey(),
                    ],
                );

                return;
            }

            ShiftRequestMonthLock::query()
                ->where('store_id', $lockedStore->getKey())
                ->where('year', $year)
                ->where('month', $month)
                ->delete();
        });
    }

    /**
     * Determine whether a store month has an explicit request lock.
     */
    public function isLocked(Store $store, int $year, int $month): bool
    {
        return ShiftRequestMonthLock::query()
            ->where('user_id', $store->getUserId())
            ->where('store_id', $store->getKey())
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }

    /**
     * Determine whether a period is later than the current month.
     */
    public function isFuturePeriod(int $year, int $month): bool
    {
        $period = CarbonImmutable::create($year, $month, 1);

        return $period instanceof CarbonImmutable &&
            $period->startOfMonth()->greaterThan(CarbonImmutable::now()->startOfMonth());
    }

    /**
     * Reject current and historical request periods.
     */
    private function assertFuturePeriod(int $year, int $month): void
    {
        if (!$this->isFuturePeriod($year, $month)) {
            Thrower::default()->message('date', \__('Shift requests can only be changed for future months.'))->throw();
        }
    }

    /**
     * Ensure the selected worker belongs to the store's company.
     */
    private function assertWorker(Store $store, Worker $worker): void
    {
        if ($worker->getUserId() !== $store->getUserId() || $worker->isArchived()) {
            Thrower::default()->message('worker_id', \__('The selected worker is invalid.'))->throw();
        }
    }

    /**
     * Ensure the actor is the admin who owns the store.
     */
    private function assertAdminStore(User $admin, Store $store): void
    {
        if (!$admin->isAdmin() || $admin->getKey() !== $store->getUserId() || !$store->isActive() || $store->isWarehouse()) {
            Thrower::default()->message('store_id', \__('The selected store is invalid.'))->throw();
        }
    }
}
