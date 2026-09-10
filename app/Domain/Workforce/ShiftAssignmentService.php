<?php

declare(strict_types=1);

namespace App\Domain\Workforce;

use App\Enums\OperationalActivityTypeEnum;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\OperationalActivityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftAssignmentService
{
    /**
     * Snapshot a shift without its hourly rate.
     *
     * @param array<string, string> $facts
     */
    public static function notify(OperationalActivityTypeEnum $type, User $actor, Store $store, Shift $shift, array $facts = []): void
    {
        $worker = Worker::query()->where('user_id', $store->getUserId())->whereKey($shift->getWorkerId())->firstOrFail();
        OperationalActivityService::dispatchForStore(
            $type,
            $actor,
            $store,
            'shifts.index',
            ['year' => (int) \mb_substr($shift->getDate(), 0, 4), 'month' => (int) \mb_substr($shift->getDate(), 5, 2)],
            [
                'Slack worker' => $worker->getFullName(), 'Slack shift date' => $shift->getDate(),
                'Slack shift time' => $shift->getStartTimeShort() . '–' . $shift->getEndTimeShort(), ...$facts,
            ],
        );
    }

    /**
     * Find an exact existing assignment.
     */
    public function findExact(
        User $user,
        Store $store,
        Worker $worker,
        string $date,
        string $startTime,
        string $endTime,
    ): Shift|null {
        $query = $this->baseQuery($user, $store, $worker, $date);
        Shift::querySelect($query);
        $shift = $query->get()->first(
            static fn(Shift $shift): bool => $startTime === $shift->getStartTimeShort() &&
                $endTime === $shift->getEndTimeShort(),
        );

        return $shift instanceof Shift ? $shift : null;
    }

    /**
     * Find assignments that overlap a proposed time range.
     *
     * @return Collection<int, Shift>
     */
    public function findOverlaps(
        User $user,
        Store $store,
        Worker $worker,
        string $date,
        string $startTime,
        string $endTime,
        int|null $excludeShiftId = null,
    ): Collection {
        $query = $this->baseQuery($user, $store, $worker, $date);

        if ($excludeShiftId !== null) {
            $query->whereKeyNot($excludeShiftId);
        }

        Shift::querySelect($query);

        return $query->orderBy('start_time')->get()->filter(
            static fn(Shift $shift): bool => $endTime > $shift->getStartTimeShort() &&
                $startTime < $shift->getEndTimeShort(),
        )->values();
    }

    /**
     * Create an assignment with the worker's current hourly rate snapshot.
     */
    public function create(
        User $user,
        Store $store,
        Worker $worker,
        string $date,
        string $startTime,
        string $endTime,
        OperationalActivityTypeEnum $activityType = OperationalActivityTypeEnum::SHIFT_CREATED,
    ): Shift {
        return DB::transaction(function () use ($user, $store, $worker, $date, $startTime, $endTime, $activityType): Shift {
            $lockedStore = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $lockedWorker = Typer::assertInstance(
                Worker::query()->whereKey($worker->getKey())->lockForUpdate()->firstOrFail(),
                Worker::class,
            );

            if ($lockedWorker->getUserId() !== $user->getKey() ||
                $lockedStore->getUserId() !== $user->getKey() ||
                !$lockedStore->isActive() ||
                $lockedStore->isWarehouse()
            ) {
                \abort(404);
            }

            if ($lockedWorker->isArchived()) {
                Thrower::default()->message('worker_id', \__('Archived workers cannot receive new work.'))->throw();
            }

            $shift = Shift::query()->create([
                'user_id' => $user->getKey(),
                'store_id' => $lockedStore->getKey(),
                'worker_id' => $lockedWorker->getKey(),
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hourly_rate' => $lockedWorker->getHourlyRate(),
            ]);
            self::notify($activityType, $user, $lockedStore, $shift);

            return $shift;
        });
    }

    /**
     * @return Builder<Shift>
     */
    private function baseQuery(User $user, Store $store, Worker $worker, string $date): Builder
    {
        $query = Shift::query();
        Shift::scopeForUser($query, $user);
        Shift::scopeForStore($query, $store->getKey());
        Shift::scopeForWorker($query, $worker->getKey());

        return $query->whereDate('date', $date);
    }
}
