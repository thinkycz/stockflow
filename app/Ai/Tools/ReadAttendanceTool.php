<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Domain\Workforce\AttendanceReportService;
use App\Models\AttendanceSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadAttendanceTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_attendance'; }

    /**
     * Explain the attendance facts available to the model.
     */
    public function description(): string { return 'Read attendance sessions, breaks, deviations, actual versus planned time, hourly rates, wages, incomplete records, and exact monthly worker totals.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $period = ['store_id' => $schema->integer()->required(), 'month' => $schema->string()->required(), 'worker_id' => $schema->integer()];

        return ['request' => $schema->anyOf([
            $schema->object(['operation' => $schema->string()->enum(['list'])->required(), 'store_id' => $schema->integer(), 'worker_id' => $schema->integer(), 'date_from' => $schema->string(), 'date_to' => $schema->string(), 'state' => $schema->string()->enum(['active', 'completed', 'voided']), 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$period])->withoutAdditionalProperties(),
        ])->required()];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array
    {
        $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        if ($operation === 'summary') {
            $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
            $month = Typer::parseNullableString($request['month'] ?? null);
            if ($storeId === null || $month === null) { throw new InvalidArgumentException('Store and month are required for attendance totals.'); }
            $report = Resolver::resolve(AttendanceReportService::class)->build($this->actor->resolveScopeUser(), $this->ownedStore($storeId, true), $month, Typer::parseNullableInt($request['worker_id'] ?? null));

            return $this->summaryResult(
                $request,
                'monthly_report',
                $report,
                $report['rows'] === [] ? 'NO_ATTENDANCE_DATA' : null,
            );
        }
        if ($operation === 'detail') {
            $session = $this->session(Typer::parseNullableInt($request['id'] ?? null));

            return $this->detailResult($request, 'sessions', $this->record($session, true));
        }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown attendance read operation.'); }
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $this->actor->resolveScopeUser());
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId);
            AttendanceSession::scopeForStore($query, $storeId); }
        $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
        if ($workerId !== null) { $query->where('worker_id', $workerId); }
        $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) { $query->where('started_at', '>=', $from . ' 00:00:00'); }
        $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) { $query->where('started_at', '<=', $to . ' 23:59:59'); }
        match (Typer::parseNullableString($request['state'] ?? null)) {
            'active' => $query->whereNull('ended_at')->whereNull('voided_at'),
            'completed' => $query->whereNotNull('ended_at')->whereNull('voided_at'),
            'voided' => $query->whereNotNull('voided_at'),
            default => null,
        };

        return $this->paginateById($query, $request, 'sessions', $request, fn(AttendanceSession $session): array => $this->record($session, false));
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'attendance'; }

    /**
     * Resolve one tenant-scoped attendance session.
     */
    private function session(int|null $id): AttendanceSession
    {
        if ($id === null) { throw new InvalidArgumentException('An attendance identifier is required.'); }
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $this->actor->resolveScopeUser());

        return $query->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function record(AttendanceSession $session, bool $includeBreaks): array
    {
        $record = [
            'id' => $session->getKey(), 'store_id' => $session->getStoreId(), 'worker_id' => $session->getWorkerId(), 'shift_id' => $session->getShiftId(),
            'scheduled_date' => $session->getScheduledDate()?->toDateString(), 'scheduled_start_time' => $session->getScheduledStartTime(), 'scheduled_end_time' => $session->getScheduledEndTime(),
            'hourly_rate' => $session->getHourlyRate(), 'started_at' => $session->getStartedAt()->toJSON(), 'ended_at' => $session->getEndedAt()?->toJSON(), 'voided_at' => $session->getVoidedAt()?->toJSON(),
            'url' => Resolver::resolveUrlGenerator()->route('attendance.index', ['store_id' => $session->getStoreId()]),
        ];
        if ($includeBreaks) {
            $record['breaks'] = $session->attendanceBreaks()->orderBy('started_at')->get()->map(static fn($break): array => ['started_at' => $break->getStartedAt()->toJSON(), 'ended_at' => $break->getEndedAt()?->toJSON()])->all();
        }

        return $record;
    }
}
