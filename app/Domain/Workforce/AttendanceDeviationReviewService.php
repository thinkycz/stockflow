<?php

declare(strict_types=1);

namespace App\Domain\Workforce;

use App\Enums\AttendanceDeviationReviewDecisionEnum;
use App\Enums\OperationalActivityTypeEnum;
use App\Enums\PayrollReportStatusEnum;
use App\Models\AttendanceDeviationReview;
use App\Models\AttendanceSession;
use App\Models\PayrollReport;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\OperationalActivityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class AttendanceDeviationReviewService
{
    /**
     * Record a review and, when approved, update the matched shift snapshots.
     */
    public function review(
        User $actor,
        Store $store,
        Shift $shift,
        AttendanceDeviationReviewDecisionEnum $decision,
        string $reason,
        string $startTime,
        string $endTime,
        bool $allowOverlap,
        CarbonImmutable $expectedStartedAt,
        CarbonImmutable $expectedEndedAt,
        string $expectedStartTime,
        string $expectedEndTime,
    ): AttendanceDeviationReview {
        return DB::transaction(function () use (
            $actor,
            $store,
            $shift,
            $decision,
            $reason,
            $startTime,
            $endTime,
            $allowOverlap,
            $expectedStartedAt,
            $expectedEndedAt,
            $expectedStartTime,
            $expectedEndTime,
        ): AttendanceDeviationReview {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $lockedShift = Shift::query()->whereKey($shift->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $store, $lockedShift);
            $sessions = $this->lockedSessions($actor, $store, $lockedShift);
            $validSessions = $sessions->filter(
                static fn(AttendanceSession $session): bool => $session->getVoidedAt() === null,
            );
            $first = $validSessions->sortBy(
                static fn(AttendanceSession $session): int => $session->getStartedAt()->getTimestamp(),
            )->first();
            $last = $validSessions->sortByDesc(
                static fn(AttendanceSession $session): int => $session->getEndedAt()?->getTimestamp() ?? 0,
            )->first();
            if (!$first instanceof AttendanceSession || !$last instanceof AttendanceSession || $validSessions->contains(
                static fn(AttendanceSession $session): bool => $session->getEndedAt() === null,
            ) || $last->getEndedAt() === null) {
                $this->fail('shift', Typer::assertString(\__('Only a completed matched shift can be reviewed.')));
            }
            if (
                $first->getStartedAt()->getTimestamp() !== $expectedStartedAt->getTimestamp() ||
                $last->getEndedAt()->getTimestamp() !== $expectedEndedAt->getTimestamp() ||
                $expectedStartTime !== $lockedShift->getStartTimeShort() ||
                $expectedEndTime !== $lockedShift->getEndTimeShort()
            ) {
                $this->fail('shift', Typer::assertString(\__('Attendance or shift times changed. Refresh the report and review again.')));
            }
            $plannedStart = CarbonImmutable::parse(
                $lockedShift->getDate() . ' ' . $lockedShift->getStartTime(),
                AttendanceService::BUSINESS_TIMEZONE,
            );
            $plannedEnd = CarbonImmutable::parse(
                $lockedShift->getDate() . ' ' . $lockedShift->getEndTime(),
                AttendanceService::BUSINESS_TIMEZONE,
            );
            $reviewQuery = AttendanceDeviationReview::query();
            AttendanceDeviationReview::scopeForUser($reviewQuery, $actor);
            AttendanceDeviationReview::querySelect($reviewQuery);
            $hasCurrentReview = $reviewQuery
                ->where('store_id', $store->getKey())
                ->where('shift_id', $lockedShift->getKey())
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->contains(
                    static fn(AttendanceDeviationReview $review): bool => $review->getActualStartedAt()->getTimestamp() === $first->getStartedAt()->getTimestamp() &&
                        $review->getActualEndedAt()->getTimestamp() === $last->getEndedAt()->getTimestamp() &&
                        \mb_substr($review->getAfterStartTime(), 0, 5) === $lockedShift->getStartTimeShort() &&
                        \mb_substr($review->getAfterEndTime(), 0, 5) === $lockedShift->getEndTimeShort(),
                );
            if (
                \abs($first->getStartedAt()->getTimestamp() - $plannedStart->utc()->getTimestamp()) <= 900 &&
                \abs($last->getEndedAt()->getTimestamp() - $plannedEnd->utc()->getTimestamp()) <= 900 &&
                !$hasCurrentReview
            ) {
                $this->fail('shift', Typer::assertString(\__('This attendance does not require a deviation review.')));
            }

            $afterStartTime = $lockedShift->getStartTimeShort();
            $afterEndTime = $lockedShift->getEndTimeShort();
            $worker = Worker::query()->whereKey($lockedShift->getWorkerId())->firstOrFail();
            if ($decision === AttendanceDeviationReviewDecisionEnum::APPROVED) {
                $this->assertPayrollOpen($actor, $store, $lockedShift);
                if (!$allowOverlap && (new ShiftAssignmentService())->findOverlaps(
                    $actor,
                    $store,
                    $worker,
                    $lockedShift->getDate(),
                    $startTime,
                    $endTime,
                    $lockedShift->getKey(),
                )->isNotEmpty()) {
                    $this->fail('overlap', Typer::assertString(\__('This shift overlaps an existing assignment.')));
                }
                $afterStartTime = $startTime;
                $afterEndTime = $endTime;
                $lockedShift->update(['start_time' => $startTime, 'end_time' => $endTime]);
                AttendanceSession::query()->where('shift_id', $lockedShift->getKey())->update([
                    'scheduled_date' => $lockedShift->getDate(),
                    'scheduled_start_time' => $startTime,
                    'scheduled_end_time' => $endTime,
                ]);
            }

            $review = AttendanceDeviationReview::query()->create([
                'user_id' => $actor->getKey(),
                'store_id' => $store->getKey(),
                'shift_id' => $lockedShift->getKey(),
                'actor_user_id' => $actor->getKey(),
                'decision' => $decision->value,
                'reason' => $reason,
                'actual_started_at' => $first->getStartedAt(),
                'actual_ended_at' => $last->getEndedAt(),
                'before_start_time' => $expectedStartTime,
                'before_end_time' => $expectedEndTime,
                'after_start_time' => $afterStartTime,
                'after_end_time' => $afterEndTime,
            ]);

            OperationalActivityService::dispatch(
                $decision === AttendanceDeviationReviewDecisionEnum::APPROVED
                    ? OperationalActivityTypeEnum::ATTENDANCE_DEVIATION_APPROVED
                    : OperationalActivityTypeEnum::ATTENDANCE_DEVIATION_REJECTED,
                $actor,
                $review->getCreatedAt()->toIso8601String(),
                Resolver::resolveUrlGenerator()->route('attendance.report', [
                    'store_id' => $store->getKey(),
                    'month' => \mb_substr($lockedShift->getDate(), 0, 7),
                ]),
                [['store' => $store, 'perspective' => null]],
                [
                    'Slack worker' => $worker->getFullName(),
                    'Slack attendance date' => $lockedShift->getDate(),
                    'Slack planned time' => $expectedStartTime . '–' . $expectedEndTime,
                    'Slack actual time' => $first->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->format('H:i') . '–' . $last->getEndedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->format('H:i'),
                    'Slack reviewed time' => $afterStartTime . '–' . $afterEndTime,
                ],
            );

            return $review;
        });
    }

    /**
     * Lock every attendance block linked to the shift.
     *
     * @return Collection<int, AttendanceSession>
     */
    private function lockedSessions(User $actor, Store $store, Shift $shift): Collection
    {
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $actor);
        AttendanceSession::scopeForStore($query, $store->getKey());
        AttendanceSession::querySelect($query);

        return $query->where('shift_id', $shift->getKey())->lockForUpdate()->get();
    }

    /**
     * Enforce the owning administrator and active store boundary.
     */
    private function authorize(User $actor, Store $store, Shift $shift): void
    {
        if (
            !$actor->isAdmin() ||
            !$store->isActive() ||
            $store->isWarehouse() ||
            $store->getUserId() !== $actor->getKey() ||
            $shift->getUserId() !== $actor->getKey() ||
            $shift->getStoreId() !== $store->getKey()
        ) {
            \abort(404);
        }
    }

    /**
     * Reject payroll-affecting approval after the monthly snapshot is closed.
     */
    private function assertPayrollOpen(User $actor, Store $store, Shift $shift): void
    {
        $query = PayrollReport::query();
        PayrollReport::scopeForUser($query, $actor);
        if ($query
            ->where('store_id', $store->getKey())
            ->where('year', (int) \mb_substr($shift->getDate(), 0, 4))
            ->where('month', (int) \mb_substr($shift->getDate(), 5, 2))
            ->where('status', PayrollReportStatusEnum::CLOSED->value)
            ->exists()) {
            $this->fail('payroll', Typer::assertString(\__('Reopen the payroll report before approving this deviation.')));
        }
    }

    /**
     * Raise a validation-style domain error.
     */
    private function fail(string $key, string $message): never
    {
        Thrower::default()->message($key, $message)->throw();
    }
}
