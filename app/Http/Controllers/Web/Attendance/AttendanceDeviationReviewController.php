<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Enums\AttendanceDeviationReviewDecisionEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\AttendanceValidity;
use App\Http\Validation\ShiftValidity;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Services\AttendanceDeviationReviewService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class AttendanceDeviationReviewController
{
    use ValidatesWebRequests;

    /**
     * Persist an administrator decision for the current attendance boundaries.
     */
    public function __invoke(Request $request, Shift $shift): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store || $store->isWarehouse()) {
            \abort(404);
        }
        $attendanceValidity = AttendanceValidity::inject($admin->getKey());
        $shiftValidity = ShiftValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, [
            'decision' => $attendanceValidity->deviationDecision()->required()->toArray(),
            'reason' => $attendanceValidity->reason()->required()->toArray(),
            'start_time' => $shiftValidity->startTime()->required()->toArray(),
            'end_time' => $shiftValidity->endTime()->required()->toArray(),
            'allow_overlap' => $shiftValidity->allowOverlap()->nullable()->toArray(),
            'expected_started_at' => $attendanceValidity->instant()->required()->toArray(),
            'expected_ended_at' => $attendanceValidity->instant()->required()->toArray(),
            'expected_start_time' => $shiftValidity->startTime()->required()->toArray(),
            'expected_end_time' => $shiftValidity->startTime()->required()->toArray(),
        ]);
        (new AttendanceDeviationReviewService())->review(
            $admin,
            $store,
            $shift,
            AttendanceDeviationReviewDecisionEnum::from($validated->assertString('decision')),
            $validated->assertString('reason'),
            $validated->assertString('start_time'),
            $validated->assertString('end_time'),
            $validated->parseBool('allow_overlap'),
            CarbonImmutable::parse($validated->assertString('expected_started_at'))->utc(),
            CarbonImmutable::parse($validated->assertString('expected_ended_at'))->utc(),
            $validated->assertString('expected_start_time'),
            $validated->assertString('expected_end_time'),
        );
        Inertia::flash('success', \__('Attendance deviation review saved.'));

        return Resolver::resolveRedirector()->route('attendance.report', [
            'month' => \mb_substr($shift->getDate(), 0, 7),
        ]);
    }
}
