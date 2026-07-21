<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceActionEnum;
use App\Models\AttendanceAudit;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class AttendanceService
{
    public const string BUSINESS_TIMEZONE = 'Europe/Prague';

    /**
     * Apply one transactional attendance state transition.
     */
    public function perform(User $actor, Store $store, Worker $worker, AttendanceActionEnum $action, bool $allowWithoutShift = false): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $store, $worker, $action, $allowWithoutShift): AttendanceSession {
            $scopeUser = $actor->resolveScopeUser();
            if ($store->isWarehouse() || $store->getUserId() !== $scopeUser->getKey() || $worker->getUserId() !== $scopeUser->getKey() ||
                (!$actor->isAdmin() && $actor->getAssignedStoreId() !== $store->getKey())) {
                $this->fail('store_id', Typer::assertString(\__('Attendance is not available for this store.')));
            }
            $now = CarbonImmutable::now('UTC');
            $active = AttendanceSession::query()->where('active_worker_id', $worker->getKey())->lockForUpdate()->first();

            if ($action === AttendanceActionEnum::ARRIVAL) {
                if ($active instanceof AttendanceSession) {
                    $this->fail('action', Typer::assertString(\__('This worker already has an open attendance session.')));
                }

                $shift = $this->findMatchingShift($scopeUser, $store, $worker, $now);
                if (!$shift instanceof Shift && !$allowWithoutShift) {
                    $this->fail('confirm_without_shift', Typer::assertString(\__('This worker has no current shift.')));
                }

                $session = AttendanceSession::query()->create([
                    'user_id' => $scopeUser->getKey(),
                    'store_id' => $store->getKey(),
                    'worker_id' => $worker->getKey(),
                    'shift_id' => $shift?->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                    'active_worker_id' => $worker->getKey(),
                    'scheduled_date' => $shift?->getDate(),
                    'scheduled_start_time' => $shift?->getStartTime(),
                    'scheduled_end_time' => $shift?->getEndTime(),
                    'hourly_rate' => $shift?->getHourlyRate() ?? $worker->getHourlyRate(),
                    'started_at' => $now,
                    'ended_at' => null,
                    'voided_at' => null,
                    'voided_by_user_id' => null,
                ]);
                $this->audit($session, $actor, $action->value);

                return $session;
            }

            if (!$active instanceof AttendanceSession || $active->getStoreId() !== $store->getKey()) {
                $this->fail('action', Typer::assertString(\__('This worker has no open attendance session at this store.')));
            }

            if ($active->getStartedAt()->setTimezone(self::BUSINESS_TIMEZONE)->toDateString() !== $now->setTimezone(self::BUSINESS_TIMEZONE)->toDateString()) {
                $this->fail('action', Typer::assertString(\__('This attendance session is stale and must be corrected by an administrator.')));
            }

            $openBreak = AttendanceBreak::query()->where('active_session_id', $active->getKey())->lockForUpdate()->first();
            if ($action === AttendanceActionEnum::BREAK_START) {
                if ($openBreak instanceof AttendanceBreak) {
                    $this->fail('action', Typer::assertString(\__('This worker is already on a break.')));
                }
                AttendanceBreak::query()->create([
                    'attendance_session_id' => $active->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                    'active_session_id' => $active->getKey(),
                    'started_at' => $now,
                ]);
            } elseif ($action === AttendanceActionEnum::BREAK_END) {
                if (!$openBreak instanceof AttendanceBreak) {
                    $this->fail('action', Typer::assertString(\__('This worker is not on a break.')));
                }
                $openBreak->update(['ended_at' => $now, 'active_session_id' => null]);
            } else {
                if ($openBreak instanceof AttendanceBreak) {
                    $openBreak->update(['ended_at' => $now, 'active_session_id' => null]);
                }
                $active->update(['ended_at' => $now, 'active_worker_id' => null]);
            }

            $this->audit($active, $actor, $action->value);

            return $active->refresh();
        });
    }

    /**
     * Find the first shift in the configured matching window.
     */
    public function findMatchingShift(User $owner, Store $store, Worker $worker, CarbonImmutable $now): Shift|null
    {
        $local = $now->setTimezone(self::BUSINESS_TIMEZONE);
        $query = Shift::query();
        Shift::scopeForUser($query, $owner);
        Shift::scopeForStore($query, $store->getKey());
        Shift::scopeForWorker($query, $worker->getKey());
        Shift::scopeForMonth($query, $local->year, $local->month);
        Shift::querySelect($query);

        foreach ($query->whereDate('date', $local->toDateString())->orderBy('start_time')->get() as $shift) {
            $start = CarbonImmutable::parse($shift->getDate() . ' ' . $shift->getStartTime(), self::BUSINESS_TIMEZONE);
            $end = CarbonImmutable::parse($shift->getDate() . ' ' . $shift->getEndTime(), self::BUSINESS_TIMEZONE);
            if ($local->betweenIncluded($start->subHour(), $end->addHour())) {
                return $shift;
            }
        }

        return null;
    }

    /**
     * Append an immutable audit event for a normal transition.
     */
    private function audit(AttendanceSession $session, User $actor, string $action): void
    {
        AttendanceAudit::query()->create([
            'attendance_session_id' => $session->getKey(),
            'actor_user_id' => $actor->getKey(),
            'action' => $action,
            'after_state' => ['session_id' => $session->getKey()],
        ]);
    }

    /**
     * Throw a validation error through the core helper.
     */
    private function fail(string $key, string $message): never
    {
        Thrower::default()->message($key, $message)->throw();
    }
}
