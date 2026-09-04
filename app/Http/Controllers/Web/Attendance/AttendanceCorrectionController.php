<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Domain\Workforce\AttendanceCorrectionService;
use App\Domain\Workforce\AttendanceService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\AttendanceValidity;
use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class AttendanceCorrectionController
{
    use ValidatesWebRequests;

    /**
     * Create a missing historical attendance session.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->storeFor($request, $admin);
        [$validated, $worker] = $this->validatedPayload($request, $admin);
        [$startedAt, $endedAt, $breaks] = $this->times($validated);
        (new AttendanceCorrectionService())->create($admin, $store, $worker, $startedAt, $endedAt, $breaks, $validated->assertString('reason'));
        Inertia::flash('success', \__('Attendance correction created.'));

        return Resolver::resolveRedirector()->route('attendance.report');
    }

    /**
     * Replace an attendance session with corrected values.
     */
    public function update(Request $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->storeFor($request, $admin);
        if ($attendanceSession->getStoreId() !== $store->getKey()) { \abort(404); }
        [$validated, $worker] = $this->validatedPayload($request, $admin);
        [$startedAt, $endedAt, $breaks] = $this->times($validated);
        (new AttendanceCorrectionService())->update($admin, $attendanceSession, $worker, $startedAt, $endedAt, $breaks, $validated->assertString('reason'));
        Inertia::flash('success', \__('Attendance correction saved.'));

        return Resolver::resolveRedirector()->route('attendance.report');
    }

    /**
     * Void an attendance session while preserving its audit trail.
     */
    public function void(Request $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->storeFor($request, $admin);
        if ($attendanceSession->getStoreId() !== $store->getKey()) { \abort(404); }
        $validity = AttendanceValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, ['reason' => $validity->reason()->required()->toArray()]);
        (new AttendanceCorrectionService())->void($admin, $attendanceSession, $validated->assertString('reason'));
        Inertia::flash('success', \__('Attendance session voided.'));

        return Resolver::resolveRedirector()->route('attendance.report');
    }

    /**
     * Validate the shared correction payload and resolve its worker.
     *
     * @return array{0: Parser, 1: Worker}
     */
    private function validatedPayload(Request $request, User $admin): array
    {
        $validity = AttendanceValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, [
            'worker_id' => $validity->workerId()->required()->toArray(),
            'started_at' => $validity->localDateTime()->required()->toArray(),
            'ended_at' => $validity->localDateTime()->required()->toArray(),
            'breaks' => $validity->breaks()->nullable()->toArray(),
            'breaks.*.started_at' => $validity->localDateTime()->required()->toArray(),
            'breaks.*.ended_at' => $validity->localDateTime()->required()->toArray(),
            'reason' => $validity->reason()->required()->toArray(),
        ]);
        $query = Worker::query();
        Worker::scopeForUser($query, $admin);

        return [$validated, $query->whereKey($validated->parseInt('worker_id'))->firstOrFail()];
    }

    /**
     * Parse browser-local correction times into UTC instants.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>}
     */
    private function times(Parser $validated): array
    {
        $startedAt = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated->assertString('started_at'), AttendanceService::BUSINESS_TIMEZONE);
        $endedAt = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated->assertString('ended_at'), AttendanceService::BUSINESS_TIMEZONE);
        if (!$startedAt instanceof CarbonImmutable || !$endedAt instanceof CarbonImmutable) { \abort(422); }
        $breaks = [];
        foreach ($validated->assertArray('breaks') as $row) {
            $values = Typer::assertArray($row);
            $breakStart = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', Typer::assertString($values['started_at'] ?? null), AttendanceService::BUSINESS_TIMEZONE);
            $breakEnd = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', Typer::assertString($values['ended_at'] ?? null), AttendanceService::BUSINESS_TIMEZONE);
            if (!$breakStart instanceof CarbonImmutable || !$breakEnd instanceof CarbonImmutable) { \abort(422); }
            $breaks[] = ['started_at' => $breakStart->utc(), 'ended_at' => $breakEnd->utc()];
        }

        return [$startedAt->utc(), $endedAt->utc(), $breaks];
    }

    /**
     * Resolve a retail store available to the administrator.
     */
    private function storeFor(Request $request, User $admin): Store
    {
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store || $store->isWarehouse()) { \abort(404); }

        return $store;
    }
}
