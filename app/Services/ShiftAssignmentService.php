<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ShiftAssignmentService
{
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
    ): Shift {
        return Shift::query()->create([
            'user_id' => $user->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $worker->getKey(),
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'hourly_rate' => $worker->getHourlyRate(),
        ]);
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
