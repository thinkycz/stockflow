<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\AttendanceReportService;
use App\Services\AttendanceService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceIndexController
{
    public const int TAKE = 1000;

    /**
     * Render attendance controls and the current store state.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $owner = $user->resolveScopeUser();
        $store = ActiveStoreResolver::resolve($request, $user);
        $props = [
            'store' => $store instanceof Store ? ['id' => $store->getKey(), 'name' => $store->getName(), 'is_warehouse' => $store->isWarehouse()] : null,
            'workers' => [], 'worker_states' => [], 'recommended_worker_id' => null,
            'store_state' => 'empty', 'today_sessions' => [], 'is_admin' => $user->isAdmin(),
        ];
        if (!$store instanceof Store || $store->isWarehouse()) {
            return Inertia::render('attendance/Index', $props);
        }

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $owner);
        Worker::querySelect($workerQuery);
        $workers = $workerQuery->orderBy('last_name')->orderBy('first_name')->take(self::TAKE)->get();
        $activeQuery = AttendanceSession::query();
        AttendanceSession::scopeForUser($activeQuery, $owner);
        AttendanceSession::scopeForStore($activeQuery, $store->getKey());
        AttendanceSession::querySelect($activeQuery);
        $active = $activeQuery->whereNotNull('active_worker_id')->get()->keyBy(static fn(AttendanceSession $session): int => $session->getWorkerId());
        $now = CarbonImmutable::now('UTC');
        $matchingWorkers = [];
        $states = [];
        $service = new AttendanceService();
        foreach ($workers as $worker) {
            $session = $active->get($worker->getKey());
            $hasShift = $service->findMatchingShift($owner, $store, $worker, $now) !== null;
            if ($hasShift) { $matchingWorkers[] = $worker->getKey(); }
            $status = 'absent';
            if ($session instanceof AttendanceSession) {
                $status = $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString()
                    !== $now->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString()
                    ? 'stale'
                    : (AttendanceBreak::query()->where('active_session_id', $session->getKey())->exists() ? 'break' : 'present');
            }
            $states[] = ['worker_id' => $worker->getKey(), 'status' => $status, 'has_current_shift' => $hasShift];
        }
        $props['workers'] = $workers->map(static fn(Worker $worker): array => [
            'id' => $worker->getKey(), 'first_name' => $worker->getFirstName(), 'last_name' => $worker->getLastName(),
        ])->all();
        $props['worker_states'] = $states;
        $activeIds = $active->keys()->all();
        $props['recommended_worker_id'] = \count($activeIds) === 1 ? $activeIds[0] : (\count($matchingWorkers) === 1 ? $matchingWorkers[0] : null);
        $reportService = new AttendanceReportService();
        $props['store_state'] = $reportService->storeState($owner, $store);
        $todayStart = CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE)->startOfDay()->utc();
        $todayQuery = AttendanceSession::query();
        AttendanceSession::scopeForUser($todayQuery, $owner);
        AttendanceSession::scopeForStore($todayQuery, $store->getKey());
        AttendanceSession::querySelect($todayQuery);
        $workerMap = $workers->keyBy(static fn(Worker $worker): int => $worker->getKey());
        $props['today_sessions'] = $todayQuery->where('started_at', '>=', $todayStart)->orderByDesc('started_at')->get()->map(
            static function (AttendanceSession $session) use ($workerMap): array {
                $worker = $workerMap->get($session->getWorkerId());

                return [
                    'id' => $session->getKey(), 'worker_id' => $session->getWorkerId(),
                    'worker_name' => $worker instanceof Worker ? $worker->getFullName() : '',
                    'started_at' => $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                    'ended_at' => $session->getEndedAt()?->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                    'breaks' => $session->attendanceBreaks()->orderBy('started_at')->get()->map(static fn(AttendanceBreak $break): array => [
                        'started_at' => $break->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                        'ended_at' => $break->getEndedAt()?->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                    ])->all(),
                ];
            },
        )->all();

        return Inertia::render('attendance/Index', $props);
    }
}
