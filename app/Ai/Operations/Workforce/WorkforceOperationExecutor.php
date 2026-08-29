<?php

declare(strict_types=1);

namespace App\Ai\Operations\Workforce;

use App\Ai\Operations\AssistantOperationExecutor;
use App\Enums\AttendanceActionEnum;
use App\Enums\AttendanceDeviationReviewDecisionEnum;
use App\Http\Validation\AttendanceValidity;
use App\Http\Validation\ShiftPresetValidity;
use App\Http\Validation\ShiftRequestValidity;
use App\Http\Validation\ShiftShareLinkValidity;
use App\Http\Validation\ShiftValidity;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\ShiftRequest;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceDeviationReviewService;
use App\Services\AttendanceService;
use App\Services\ShiftRequestService;
use App\Services\WorkforceManagementService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class WorkforceOperationExecutor implements AssistantOperationExecutor
{
    /**
     * Create the service-backed workforce executor.
     */
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly AttendanceCorrectionService $corrections,
        private readonly AttendanceDeviationReviewService $deviations,
        private readonly ShiftRequestService $requests,
        private readonly WorkforceManagementService $workforce,
    ) {}

    /**
     * Validate a proposal and resolve its exact store and target.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function preview(string $identifier, User $actor, array $arguments): array
    {
        $this->assertAdmin($actor);
        $store = $this->store($actor, Typer::assertInt(Typer::parseNullableInt($arguments['store_id'] ?? null)));
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $this->validate($identifier, $actor, $store, $targetId, $context, $values);
        $targetType = $this->resolveTarget($identifier, $actor, $store, $targetId, $context);

        return [
            'operation' => $identifier,
            'store' => ['id' => $store->getKey(), 'name' => $store->getName()],
            'target' => $targetId === null ? null : ['type' => $targetType, 'id' => (string) $targetId],
            'effects' => $this->effects($identifier),
            'sanitized_arguments' => ['context' => $context, 'values' => $values],
            'safe_editable_fields' => ['values_json'],
        ];
    }

    /**
     * Execute one approved workforce action through the human-facing service.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(string $identifier, User $actor, array $arguments): array
    {
        $this->assertAdmin($actor);
        $store = $this->store($actor, Typer::assertInt(Typer::parseNullableInt($arguments['store_id'] ?? null)));
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $this->validate($identifier, $actor, $store, $targetId, $context, $values);
        $this->resolveTarget($identifier, $actor, $store, $targetId, $context);
        $recordId = $this->run($identifier, $actor, $store, $targetId, $context, $values);

        return [
            'operation' => $identifier,
            'status' => 'succeeded',
            'record' => [
                'type' => $this->resultType($identifier),
                'id' => $recordId,
                'store_id' => $store->getKey(),
                'url' => $this->url($identifier, $recordId),
            ],
        ];
    }

    /**
     * Execute a fixed workforce operation.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function run(string $identifier, User $actor, Store $store, int|null $targetId, array $context, array $values): int
    {
        if ($identifier === 'record_attendance_action') {
            return $this->attendance->perform(
                $actor,
                $store,
                $this->worker($actor, Typer::assertInt($targetId)),
                AttendanceActionEnum::from(Typer::assertString($values['action'] ?? null)),
                Typer::parseBool($values['confirm_without_shift'] ?? false),
            )->getKey();
        }

        if (\in_array($identifier, ['create_attendance_correction', 'update_attendance_correction'], true)) {
            [$startedAt, $endedAt, $breaks] = $this->attendanceTimes($values);
            $worker = $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null));

            if ($identifier === 'create_attendance_correction') {
                return $this->corrections->create(
                    $actor,
                    $store,
                    $worker,
                    $startedAt,
                    $endedAt,
                    $breaks,
                    Typer::assertString($values['reason'] ?? null),
                )->getKey();
            }

            return $this->corrections->update(
                $actor,
                $this->attendanceSession($actor, $store, Typer::assertInt($targetId)),
                $worker,
                $startedAt,
                $endedAt,
                $breaks,
                Typer::assertString($values['reason'] ?? null),
            )->getKey();
        }

        if ($identifier === 'void_attendance_session') {
            return $this->corrections->void(
                $actor,
                $this->attendanceSession($actor, $store, Typer::assertInt($targetId)),
                Typer::assertString($values['reason'] ?? null),
            )->getKey();
        }

        if ($identifier === 'review_attendance_deviation') {
            return $this->deviations->review(
                $actor,
                $store,
                $this->shift($actor, $store, Typer::assertInt($targetId)),
                AttendanceDeviationReviewDecisionEnum::from(Typer::assertString($values['decision'] ?? null)),
                Typer::assertString($values['reason'] ?? null),
                Typer::assertString($values['start_time'] ?? null),
                Typer::assertString($values['end_time'] ?? null),
                Typer::parseBool($values['allow_overlap'] ?? false),
                CarbonImmutable::parse(Typer::assertString($context['expected_started_at'] ?? null))->utc(),
                CarbonImmutable::parse(Typer::assertString($context['expected_ended_at'] ?? null))->utc(),
                Typer::assertString($context['expected_start_time'] ?? null),
                Typer::assertString($context['expected_end_time'] ?? null),
            )->getKey();
        }

        if ($identifier === 'create_shift') {
            return $this->workforce->createShift(
                $actor,
                $store,
                $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null)),
                Typer::assertString($values['date'] ?? null),
                Typer::assertString($values['start_time'] ?? null),
                Typer::assertString($values['end_time'] ?? null),
                Typer::parseBool($values['allow_overlap'] ?? false),
            )->getKey();
        }

        if ($identifier === 'quick_add_shift') {
            $result = $this->workforce->quickAddShift(
                $actor,
                $store,
                $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null)),
                $this->preset($actor, $store, Typer::assertInt($targetId)),
                Typer::assertString($values['date'] ?? null),
                Typer::parseBool($values['allow_overlap'] ?? false),
            );

            return $result['shift']->getKey();
        }

        if ($identifier === 'update_shift') {
            return $this->workforce->updateShift(
                $actor,
                $store,
                $this->shift($actor, $store, Typer::assertInt($targetId)),
                $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null)),
                Typer::assertString($values['date'] ?? null),
                Typer::assertString($values['start_time'] ?? null),
                Typer::assertString($values['end_time'] ?? null),
                Typer::parseBool($values['allow_overlap'] ?? false),
            )->getKey();
        }

        if ($identifier === 'delete_shift') {
            $shift = $this->shift($actor, $store, Typer::assertInt($targetId));
            $this->workforce->deleteShift($actor, $store, $shift);

            return $shift->getKey();
        }

        if ($identifier === 'create_shift_preset') {
            return $this->workforce->createPreset(
                $actor,
                $store,
                \mb_trim(Typer::assertString($values['name'] ?? null)),
                Typer::assertString($values['start_time'] ?? null),
                Typer::assertString($values['end_time'] ?? null),
            )->getKey();
        }

        if ($identifier === 'update_shift_preset') {
            return $this->workforce->updatePreset(
                $actor,
                $store,
                $this->preset($actor, $store, Typer::assertInt($targetId)),
                \mb_trim(Typer::assertString($values['name'] ?? null)),
                Typer::assertString($values['start_time'] ?? null),
                Typer::assertString($values['end_time'] ?? null),
            )->getKey();
        }

        if ($identifier === 'delete_shift_preset') {
            $preset = $this->preset($actor, $store, Typer::assertInt($targetId));
            $this->workforce->deletePreset($actor, $store, $preset);

            return $preset->getKey();
        }

        if ($identifier === 'set_shift_request_lock') {
            $this->requests->setLocked(
                $actor,
                $store,
                Typer::parseInt($values['year'] ?? null),
                Typer::parseInt($values['month'] ?? null),
                Typer::parseBool($values['locked'] ?? null),
            );

            return $store->getKey();
        }

        if ($identifier === 'toggle_shift_request') {
            $result = $this->requests->toggle(
                $store,
                $this->worker($actor, Typer::assertInt($targetId)),
                Typer::assertString($values['date'] ?? null),
                Typer::assertString($values['start_time'] ?? null),
                Typer::assertString($values['end_time'] ?? null),
            );

            return $result['request']?->getKey() ?? Typer::assertInt($targetId);
        }

        if ($identifier === 'approve_shift_request') {
            return $this->requests->approve(
                $actor,
                $store,
                Typer::assertInt($targetId),
                Typer::assertString($values['start_time'] ?? null),
                Typer::assertString($values['end_time'] ?? null),
                Typer::parseBool($values['allow_overlap'] ?? false),
            )->getKey();
        }

        if ($identifier === 'create_shift_share_link') {
            return $this->workforce->createShareLink($actor, $store, \mb_trim(Typer::assertString($values['name'] ?? null)))->getKey();
        }

        if ($identifier === 'revoke_shift_share_link') {
            $link = $this->shareLink($actor, $store, Typer::assertInt($targetId));
            $this->workforce->deleteShareLink($actor, $store, $link);

            return $link->getKey();
        }

        throw new InvalidArgumentException('Unknown workforce operation.');
    }

    /**
     * Validate values using the same form validity classes as the web UI.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function validate(string $identifier, User $actor, Store $store, int|null $targetId, array $context, array $values): void
    {
        $attendance = AttendanceValidity::inject($actor->getKey());
        $shift = ShiftValidity::inject($actor->getKey());
        $request = ShiftRequestValidity::inject($actor->getKey());
        $payload = [...$context, ...$values];
        $rules = match ($identifier) {
            'record_attendance_action' => [
                'action' => $attendance->action()->required()->toArray(),
                'confirm_without_shift' => $attendance->confirmation()->nullable()->toArray(),
            ],
            'create_attendance_correction', 'update_attendance_correction' => [
                'worker_id' => $attendance->workerId()->required()->toArray(),
                'started_at' => $attendance->localDateTime()->required()->toArray(),
                'ended_at' => $attendance->localDateTime()->required()->toArray(),
                'breaks' => $attendance->breaks()->nullable()->toArray(),
                'breaks.*.started_at' => $attendance->localDateTime()->required()->toArray(),
                'breaks.*.ended_at' => $attendance->localDateTime()->required()->toArray(),
                'reason' => $attendance->reason()->required()->toArray(),
            ],
            'void_attendance_session' => ['reason' => $attendance->reason()->required()->toArray()],
            'review_attendance_deviation' => [
                'decision' => $attendance->deviationDecision()->required()->toArray(),
                'reason' => $attendance->reason()->required()->toArray(),
                'start_time' => $shift->startTime()->required()->toArray(),
                'end_time' => $shift->endTime()->required()->toArray(),
                'allow_overlap' => $shift->allowOverlap()->nullable()->toArray(),
                'expected_started_at' => $attendance->instant()->required()->toArray(),
                'expected_ended_at' => $attendance->instant()->required()->toArray(),
                'expected_start_time' => $shift->startTime()->required()->toArray(),
                'expected_end_time' => $shift->startTime()->required()->toArray(),
            ],
            'create_shift', 'update_shift' => [
                'worker_id' => $shift->workerId()->required()->toArray(),
                'date' => $shift->date()->required()->toArray(),
                'start_time' => $shift->startTime()->required()->toArray(),
                'end_time' => $shift->endTime()->required()->toArray(),
                'allow_overlap' => $shift->allowOverlap()->nullable()->toArray(),
            ],
            'quick_add_shift' => [
                'worker_id' => $shift->workerId()->required()->toArray(),
                'date' => $shift->date()->required()->toArray(),
                'allow_overlap' => $shift->allowOverlap()->nullable()->toArray(),
            ],
            'delete_shift', 'delete_shift_preset', 'revoke_shift_share_link' => [],
            'create_shift_preset', 'update_shift_preset' => [
                'name' => ShiftPresetValidity::inject($store->getKey(), $identifier === 'update_shift_preset' ? $targetId : null)->name()->required()->toArray(),
                'start_time' => ShiftPresetValidity::inject($store->getKey(), $identifier === 'update_shift_preset' ? $targetId : null)->startTime()->required()->toArray(),
                'end_time' => ShiftPresetValidity::inject($store->getKey(), $identifier === 'update_shift_preset' ? $targetId : null)->endTime()->required()->toArray(),
            ],
            'set_shift_request_lock' => [
                'year' => $request->year()->required()->toArray(),
                'month' => $request->month()->required()->toArray(),
                'locked' => $request->locked()->required()->toArray(),
            ],
            'toggle_shift_request' => [
                'date' => $request->date()->required()->toArray(),
                'start_time' => $request->startTime()->required()->toArray(),
                'end_time' => $request->endTime()->required()->toArray(),
            ],
            'approve_shift_request' => [
                'start_time' => $shift->startTime()->required()->toArray(),
                'end_time' => $shift->endTime()->required()->toArray(),
                'allow_overlap' => $shift->allowOverlap()->nullable()->toArray(),
            ],
            'create_shift_share_link' => [
                'name' => ShiftShareLinkValidity::inject($store->getKey())->name()->required()->toArray(),
            ],
            default => throw new InvalidArgumentException('Unknown workforce operation.'),
        };

        Resolver::resolveValidatorFactory()->make($payload, $rules)->validate();

        if ($identifier === 'record_attendance_action' || $identifier === 'toggle_shift_request') {
            $this->worker($actor, Typer::assertInt($targetId));
        }
    }

    /**
     * Resolve an operation target and enforce tenant/store ownership.
     *
     * @param array<string, mixed> $context
     */
    private function resolveTarget(string $identifier, User $actor, Store $store, int|null $targetId, array $context): string|null
    {
        return match ($identifier) {
            'record_attendance_action', 'toggle_shift_request' => $this->resolvedWorker($actor, Typer::assertInt($targetId)),
            'update_attendance_correction', 'void_attendance_session' => $this->resolvedAttendanceSession($actor, $store, Typer::assertInt($targetId)),
            'review_attendance_deviation', 'update_shift', 'delete_shift' => $this->resolvedShift($actor, $store, Typer::assertInt($targetId)),
            'quick_add_shift', 'update_shift_preset', 'delete_shift_preset' => $this->resolvedPreset($actor, $store, Typer::assertInt($targetId)),
            'approve_shift_request' => $this->resolvedShiftRequest($actor, $store, Typer::assertInt($targetId)),
            'revoke_shift_share_link' => $this->resolvedShareLink($actor, $store, Typer::assertInt($targetId)),
            'create_attendance_correction', 'create_shift' => $this->resolvedWorkerContext($actor, $context),
            'create_shift_preset', 'set_shift_request_lock', 'create_shift_share_link' => null,
            default => throw new InvalidArgumentException('Unknown workforce operation.'),
        };
    }

    /**
     * Resolve the worker locked into an operation context.
     *
     * @param array<string, mixed> $context
     */
    private function resolvedWorkerContext(User $actor, array $context): string
    {
        $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null));

        return 'worker';
    }

    /**
     * Resolve an owned worker target type.
     */
    private function resolvedWorker(User $actor, int $id): string
    {
        $this->worker($actor, $id);

        return 'worker';
    }

    /**
     * Resolve an owned attendance-session target type.
     */
    private function resolvedAttendanceSession(User $actor, Store $store, int $id): string
    {
        $this->attendanceSession($actor, $store, $id);

        return 'attendance_session';
    }

    /**
     * Resolve an owned shift target type.
     */
    private function resolvedShift(User $actor, Store $store, int $id): string
    {
        $this->shift($actor, $store, $id);

        return 'shift';
    }

    /**
     * Resolve an owned preset target type.
     */
    private function resolvedPreset(User $actor, Store $store, int $id): string
    {
        $this->preset($actor, $store, $id);

        return 'shift_preset';
    }

    /**
     * Resolve an owned request target type.
     */
    private function resolvedShiftRequest(User $actor, Store $store, int $id): string
    {
        $this->shiftRequest($actor, $store, $id);

        return 'shift_request';
    }

    /**
     * Resolve an owned public-link target type.
     */
    private function resolvedShareLink(User $actor, Store $store, int $id): string
    {
        $this->shareLink($actor, $store, $id);

        return 'shift_share_link';
    }

    /**
     * Resolve an owned store.
     */
    private function store(User $actor, int $id): Store
    {
        return Typer::assertInstance(Store::query()->where('user_id', $actor->getKey())->whereKey($id)->firstOrFail(), Store::class);
    }

    /**
     * Resolve an owned worker.
     */
    private function worker(User $actor, int $id): Worker
    {
        return Typer::assertInstance(Worker::query()->where('user_id', $actor->getKey())->whereKey($id)->firstOrFail(), Worker::class);
    }

    /**
     * Resolve an owned attendance session inside a store.
     */
    private function attendanceSession(User $actor, Store $store, int $id): AttendanceSession
    {
        return Typer::assertInstance(AttendanceSession::query()
            ->where('user_id', $actor->getKey())
            ->where('store_id', $store->getKey())
            ->whereKey($id)
            ->firstOrFail(), AttendanceSession::class);
    }

    /**
     * Resolve an owned shift inside a store.
     */
    private function shift(User $actor, Store $store, int $id): Shift
    {
        return Typer::assertInstance(Shift::query()
            ->where('user_id', $actor->getKey())
            ->where('store_id', $store->getKey())
            ->whereKey($id)
            ->firstOrFail(), Shift::class);
    }

    /**
     * Resolve an owned preset inside a store.
     */
    private function preset(User $actor, Store $store, int $id): ShiftPreset
    {
        return Typer::assertInstance(ShiftPreset::query()
            ->where('user_id', $actor->getKey())
            ->where('store_id', $store->getKey())
            ->whereKey($id)
            ->firstOrFail(), ShiftPreset::class);
    }

    /**
     * Resolve an owned request inside a store.
     */
    private function shiftRequest(User $actor, Store $store, int $id): ShiftRequest
    {
        return Typer::assertInstance(ShiftRequest::query()
            ->where('user_id', $actor->getKey())
            ->where('store_id', $store->getKey())
            ->whereKey($id)
            ->firstOrFail(), ShiftRequest::class);
    }

    /**
     * Resolve an owned public link without exposing its token.
     */
    private function shareLink(User $actor, Store $store, int $id): ShiftShareLink
    {
        return Typer::assertInstance(ShiftShareLink::query()
            ->where('user_id', $actor->getKey())
            ->where('store_id', $store->getKey())
            ->whereKey($id)
            ->firstOrFail(), ShiftShareLink::class);
    }

    /**
     * Parse browser-local correction values exactly like the web controller.
     *
     * @param array<string, mixed> $values
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>}
     */
    private function attendanceTimes(array $values): array
    {
        $startedAt = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', Typer::assertString($values['started_at'] ?? null), AttendanceService::BUSINESS_TIMEZONE);
        $endedAt = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', Typer::assertString($values['ended_at'] ?? null), AttendanceService::BUSINESS_TIMEZONE);

        if (!$startedAt instanceof CarbonImmutable || !$endedAt instanceof CarbonImmutable) {
            \abort(422);
        }

        $breaks = [];

        foreach (Typer::assertArray($values['breaks'] ?? []) as $breakValue) {
            $break = Typer::assertStringKeyArray(Typer::assertArray($breakValue));
            $breakStart = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', Typer::assertString($break['started_at'] ?? null), AttendanceService::BUSINESS_TIMEZONE);
            $breakEnd = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', Typer::assertString($break['ended_at'] ?? null), AttendanceService::BUSINESS_TIMEZONE);

            if (!$breakStart instanceof CarbonImmutable || !$breakEnd instanceof CarbonImmutable) {
                \abort(422);
            }

            $breaks[] = ['started_at' => $breakStart->utc(), 'ended_at' => $breakEnd->utc()];
        }

        return [$startedAt->utc(), $endedAt->utc(), $breaks];
    }

    /**
     * Ensure only the main administrator can invoke workforce tools.
     */
    private function assertAdmin(User $actor): void
    {
        if (!$actor->isAdmin()) {
            \abort(403);
        }
    }

    /**
     * Describe the durable business effect shown before approval.
     */
    private function effects(string $identifier): string
    {
        return match ($identifier) {
            'record_attendance_action' => 'Creates the normal attendance session, break, audit, and operational activity for the transition.',
            'create_attendance_correction' => 'Creates a completed attendance session with correction audit and activity records.',
            'update_attendance_correction' => 'Replaces the attendance times and breaks while preserving the correction audit trail.',
            'void_attendance_session' => 'Voids the selected session and records the normal audit and activity.',
            'review_attendance_deviation' => 'Records the review and may update shift and attendance schedule snapshots.',
            'create_shift', 'quick_add_shift' => 'Creates a shift with the worker’s current hourly-rate snapshot.',
            'update_shift' => 'Updates the selected shift and refreshes the wage snapshot when its worker changes.',
            'delete_shift' => 'Deletes the selected shift.',
            'create_shift_preset' => 'Creates a reusable shift preset.',
            'update_shift_preset' => 'Updates the selected shift preset.',
            'delete_shift_preset' => 'Deletes the selected shift preset.',
            'set_shift_request_lock' => 'Locks or reopens public requests for the selected future month.',
            'toggle_shift_request' => 'Creates, updates, or removes the worker’s request for the selected day.',
            'approve_shift_request' => 'Creates the requested shift and removes the approved request transactionally.',
            'create_shift_share_link' => 'Creates an unguessable public shift link.',
            'revoke_shift_share_link' => 'Revokes the selected public shift link.',
            default => throw new InvalidArgumentException('Unknown workforce operation.'),
        };
    }

    /**
     * Resolve the operation result type.
     */
    private function resultType(string $identifier): string
    {
        return match (true) {
            \str_contains($identifier, 'attendance') => \str_contains($identifier, 'deviation') ? 'attendance_deviation_review' : 'attendance_session',
            \str_contains($identifier, 'preset') => 'shift_preset',
            \str_contains($identifier, 'share_link') => 'shift_share_link',
            \str_contains($identifier, 'request') => 'shift_request',
            default => 'shift',
        };
    }

    /**
     * Build a normal in-app result link without exposing public tokens.
     */
    private function url(string $identifier, int $recordId): string
    {
        if (\str_contains($identifier, 'attendance')) {
            return Resolver::resolveUrlGenerator()->route('attendance.report');
        }

        return Resolver::resolveUrlGenerator()->route('shifts.index');
    }

    /**
     * Decode a bounded JSON object from the mutation envelope.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function json(array $arguments, string $key): array
    {
        $json = Typer::parseNullableString($arguments[$key] ?? null) ?? '{}';

        if (\mb_strlen($json) > 50_000) {
            throw new InvalidArgumentException('Assistant operation arguments are too large.');
        }

        return Typer::assertStringKeyArray(Typer::assertArray(\json_decode($json, true, 32, \JSON_THROW_ON_ERROR)));
    }
}
