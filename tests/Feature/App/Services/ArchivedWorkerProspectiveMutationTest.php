<?php

declare(strict_types=1);

use App\Enums\AttendanceActionEnum;
use App\Enums\PayrollAdjustmentTypeEnum;
use App\Models\AttendanceSession;
use App\Models\ChecklistItem;
use App\Models\Recipe;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceService;
use App\Services\ChecklistService;
use App\Services\PayrollReportService;
use App\Services\RecipeCatalogService;
use App\Services\RecipeTestService;
use App\Services\RecipeTestSessionService;
use App\Services\ShiftAssignmentService;
use App\Services\ShiftRequestService;
use App\Services\WorkforceManagementService;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Validation\ValidationException;
use Thinkycz\LaravelCore\Support\Typer;

\test('prospective mutations recheck the locked worker after archival', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $checklistDay = (new ChecklistService())->ensureDay($store, CarbonImmutable::now(ChecklistService::TIMEZONE));
    $checklistItem = Typer::assertInstance($checklistDay->items()->firstOrFail(), ChecklistItem::class);
    (new RecipeCatalogService())->initialize($admin);
    $recipe = Typer::assertInstance(Recipe::query()->where('user_id', $admin->getKey())->firstOrFail(), Recipe::class);
    $historicalShift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => CarbonImmutable::today()->subMonth()->toDateString(),
    ]);
    $worker->newQuery()->whereKey($worker->getKey())->update(['archived_at' => CarbonImmutable::now()]);
    $future = CarbonImmutable::today()->addMonth()->toDateString();

    \expect(fn() => (new ShiftAssignmentService())->create($admin, $store, $worker, $future, '08:00', '09:00'))
        ->toThrow(ValidationException::class)
        ->and(fn() => (new WorkforceManagementService())->updateShift(
            $admin,
            $store,
            $historicalShift,
            $worker,
            $future,
            '08:00',
            '09:00',
            false,
        ))->toThrow(ValidationException::class)
        ->and(fn() => (new AttendanceService())->perform(
            $admin,
            $store,
            $worker,
            AttendanceActionEnum::ARRIVAL,
            true,
        ))->toThrow(ValidationException::class)
        ->and(fn() => (new ShiftRequestService())->toggle($store, $worker, $future, '08:00', '09:00'))
        ->toThrow(ValidationException::class);

    $payroll = new PayrollReportService();
    \expect(fn() => $payroll->addWorker($admin, $store, 2026, 7, $worker))
        ->toThrow(ValidationException::class)
        ->and(fn() => $payroll->upsertWageOverride($admin, $store, 2026, 7, $worker, 1, 100))
        ->toThrow(ValidationException::class)
        ->and(fn() => $payroll->createAdjustment(
            $admin,
            $store,
            2026,
            7,
            $worker,
            PayrollAdjustmentTypeEnum::TIP,
            1,
            'Late tip',
        ))->toThrow(ValidationException::class);

    $corrections = new AttendanceCorrectionService();
    $startedAt = CarbonImmutable::parse('2026-07-20 06:00:00 UTC');
    $endedAt = CarbonImmutable::parse('2026-07-20 14:00:00 UTC');
    \expect(fn() => $corrections->create($admin, $store, $worker, $startedAt, $endedAt, [], 'Late correction'))
        ->toThrow(ValidationException::class);

    $activeWorker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $activeWorker->getKey(),
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
    ]);
    \expect(fn() => $corrections->update($admin, $session, $worker, $startedAt, $endedAt, [], 'Wrong worker'))
        ->toThrow(ValidationException::class);

    $historicalSession = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
    ]);
    \expect($corrections->update($admin, $historicalSession, $worker, $startedAt, $endedAt, [], 'Historical correction')->getWorkerId())
        ->toBe($worker->getKey());

    \expect(fn() => (new ChecklistService())->updateItem(
        $checklistItem,
        $store,
        $limited,
        $worker,
        true,
        $checklistItem->getLockVersion(),
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn() => (new RecipeTestService())->start($limited, $worker, $recipe))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn() => (new RecipeTestSessionService())->start($limited, $worker))
        ->toThrow(InvalidArgumentException::class);
});
