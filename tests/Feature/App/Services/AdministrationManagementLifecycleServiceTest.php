<?php

declare(strict_types=1);

use App\Domain\Stores\StoreManagementService;
use App\Domain\Workforce\WorkerManagementService;
use App\Enums\RemovalOutcomeEnum;
use App\Enums\StoreStatusEnum;
use App\Models\AssistantActionAudit;
use App\Models\AttendanceSession;
use App\Models\BankStatement;
use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplateTask;
use App\Models\FinancialRecurringExpense;
use App\Models\FinancialReport;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherEvent;
use App\Models\InventorySession;
use App\Models\NoticeboardCard;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use App\Models\ShiftShareLink;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

\test('every worker foreign-key family archives the worker and preserves its row', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $otherWorker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $payrollReportId = DB::table('payroll_reports')->insertGetId([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'year' => 2025,
        'month' => 1,
        'status' => 'open',
        'created_at' => \now(),
        'updated_at' => \now(),
    ]);
    $checklistDay = ChecklistDay::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'date' => '2025-01-01',
    ]);
    $recipeCategory = RecipeCategory::factory()->create(['user_id' => $admin->getKey()]);
    $recipe = Recipe::factory()->create([
        'user_id' => $admin->getKey(),
        'recipe_category_id' => $recipeCategory->getKey(),
    ]);

    /** @var array<string, callable(Worker): array{table: string, id: int}> $references */
    $references = [
        'shifts.worker_id' => static function (Worker $worker) use ($admin, $store): array {
            $model = Shift::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $worker->getKey(),
                'date' => CarbonImmutable::today()->subDay()->toDateString(),
            ]);

            return ['table' => 'shifts', 'id' => $model->getKey()];
        },
        'shift_requests.worker_id' => static function (Worker $worker) use ($admin, $store): array {
            $model = ShiftRequest::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $worker->getKey(),
                'date' => CarbonImmutable::today()->subDay()->toDateString(),
            ]);

            return ['table' => 'shift_requests', 'id' => $model->getKey()];
        },
        'attendance_sessions.worker_id' => static function (Worker $worker) use ($admin, $store): array {
            $model = AttendanceSession::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $worker->getKey(),
                'active_worker_id' => null,
                'ended_at' => CarbonImmutable::now(),
            ]);

            return ['table' => 'attendance_sessions', 'id' => $model->getKey()];
        },
        'attendance_sessions.active_worker_id' => static function (Worker $worker) use ($admin, $store, $otherWorker): array {
            $model = AttendanceSession::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $otherWorker->getKey(),
                'active_worker_id' => $worker->getKey(),
                'ended_at' => CarbonImmutable::now(),
            ]);

            return ['table' => 'attendance_sessions', 'id' => $model->getKey()];
        },
        'payroll_adjustments.worker_id' => static function (Worker $worker) use ($payrollReportId): array {
            $id = DB::table('payroll_adjustments')->insertGetId([
                'payroll_report_id' => $payrollReportId,
                'worker_id' => $worker->getKey(),
                'type' => 'tip',
                'amount' => '1.00',
                'reason' => 'Historical adjustment',
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);

            return ['table' => 'payroll_adjustments', 'id' => $id];
        },
        'payroll_wage_overrides.worker_id' => static function (Worker $worker) use ($payrollReportId): array {
            $id = DB::table('payroll_wage_overrides')->insertGetId([
                'payroll_report_id' => $payrollReportId,
                'worker_id' => $worker->getKey(),
                'hours' => '1.00',
                'hourly_rate' => '100.00',
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);

            return ['table' => 'payroll_wage_overrides', 'id' => $id];
        },
        'payroll_worker_entries.worker_id' => static function (Worker $worker) use ($payrollReportId): array {
            $id = DB::table('payroll_worker_entries')->insertGetId([
                'payroll_report_id' => $payrollReportId,
                'worker_id' => $worker->getKey(),
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);

            return ['table' => 'payroll_worker_entries', 'id' => $id];
        },
        'checklist_items.completed_by_worker_id' => static function (Worker $worker) use ($checklistDay): array {
            $model = ChecklistItem::factory()->create([
                'checklist_day_id' => $checklistDay->getKey(),
                'completed_by_worker_id' => $worker->getKey(),
                'completed_at' => CarbonImmutable::now(),
            ]);

            return ['table' => 'checklist_items', 'id' => $model->getKey()];
        },
        'checklist_events.worker_id' => static function (Worker $worker) use ($checklistDay): array {
            $id = DB::table('checklist_events')->insertGetId([
                'checklist_day_id' => $checklistDay->getKey(),
                'checklist_item_id' => null,
                'actor_user_id' => null,
                'worker_id' => $worker->getKey(),
                'action' => 'completed',
                'reason' => null,
                'created_at' => \now(),
            ]);

            return ['table' => 'checklist_events', 'id' => $id];
        },
        'recipe_test_attempts.worker_id' => static function (Worker $worker) use ($admin, $recipe): array {
            $id = DB::table('recipe_test_attempts')->insertGetId([
                'user_id' => $admin->getKey(),
                'recipe_id' => $recipe->getKey(),
                'recipe_variant_id' => null,
                'worker_id' => $worker->getKey(),
                'actor_user_id' => null,
                'recipe_name' => $recipe->getName(),
                'variant_name' => null,
                'worker_name' => $worker->getFullName(),
                'actor_name' => $admin->getEmail(),
                'correct_steps' => \json_encode(['first', 'second'], \JSON_THROW_ON_ERROR),
                'presented_tokens' => \json_encode(['second', 'first'], \JSON_THROW_ON_ERROR),
                'submitted_tokens' => null,
                'score' => null,
                'passed' => null,
                'started_at' => \now(),
                'submitted_at' => null,
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);

            return ['table' => 'recipe_test_attempts', 'id' => $id];
        },
        'recipe_test_sessions.worker_id' => static function (Worker $worker) use ($admin): array {
            $id = DB::table('recipe_test_sessions')->insertGetId([
                'user_id' => $admin->getKey(),
                'worker_id' => $worker->getKey(),
                'actor_user_id' => null,
                'worker_name' => $worker->getFullName(),
                'actor_name' => $admin->getEmail(),
                'score' => null,
                'passed' => null,
                'started_at' => \now(),
                'submitted_at' => null,
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);

            return ['table' => 'recipe_test_sessions', 'id' => $id];
        },
    ];

    foreach ($references as $family => $createReference) {
        $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
        $reference = $createReference($worker);

        \expect((new WorkerManagementService())->deleteWorker($admin, $worker))->toBe(RemovalOutcomeEnum::ARCHIVED, $family)
            ->and($worker->refresh()->isArchived())->toBeTrue($family)
            ->and(DB::table($reference['table'])->where('id', $reference['id'])->exists())->toBeTrue($family);
    }
});

\test('every store history family deactivates the store and preserves its row', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();

    /** @var array<string, callable(Store): array{table: string, id: int}> $references */
    $references = [
        'stock_movements.store_id' => static function (Store $store) use ($admin): array {
            $model = StockMovement::factory()->incoming()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'created_by' => $admin->getKey(),
            ]);

            return ['table' => 'stock_movements', 'id' => $model->getKey()];
        },
        'stock_movements.source_store_id' => static function (Store $store) use ($admin): array {
            $destination = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
            $model = StockMovement::factory()->outgoing($destination)->create([
                'user_id' => $admin->getKey(),
                'source_store_id' => $store->getKey(),
                'created_by' => $admin->getKey(),
            ]);

            return ['table' => 'stock_movements', 'id' => $model->getKey()];
        },
        'inventory_sessions.store_id' => static function (Store $store) use ($admin): array {
            $model = InventorySession::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'status' => 'closed',
                'active_store_key' => null,
                'closed_at' => \now(),
                'cancelled_at' => null,
            ]);

            return ['table' => 'inventory_sessions', 'id' => $model->getKey()];
        },
        'statements.store_id' => static function (Store $store) use ($admin): array {
            $model = Statement::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey()]);

            return ['table' => 'statements', 'id' => $model->getKey()];
        },
        'shifts.store_id' => static function (Store $store) use ($admin): array {
            $model = Shift::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => Worker::factory()->create(['user_id' => $admin->getKey()])->getKey(),
                'date' => CarbonImmutable::today()->subDay()->toDateString(),
            ]);

            return ['table' => 'shifts', 'id' => $model->getKey()];
        },
        'shift_presets.store_id' => static function (Store $store) use ($admin): array {
            $model = ShiftPreset::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey()]);

            return ['table' => 'shift_presets', 'id' => $model->getKey()];
        },
        'attendance_sessions.store_id' => static function (Store $store) use ($admin): array {
            $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
            $model = AttendanceSession::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => $worker->getKey(),
                'active_worker_id' => null,
                'ended_at' => \now(),
            ]);

            return ['table' => 'attendance_sessions', 'id' => $model->getKey()];
        },
        'attendance_deviation_reviews.store_id' => static function (Store $store) use ($admin): array {
            $shift = Shift::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => Worker::factory()->create(['user_id' => $admin->getKey()])->getKey(),
                'date' => CarbonImmutable::today()->subDay()->toDateString(),
            ]);
            $id = DB::table('attendance_deviation_reviews')->insertGetId([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'shift_id' => $shift->getKey(),
                'actor_user_id' => $admin->getKey(),
                'decision' => 'accepted',
                'reason' => 'Historical correction',
                'actual_started_at' => CarbonImmutable::today()->subDay()->setTime(9, 0),
                'actual_ended_at' => CarbonImmutable::today()->subDay()->setTime(17, 0),
                'before_start_time' => '09:00',
                'before_end_time' => '17:00',
                'after_start_time' => '09:00',
                'after_end_time' => '17:00',
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);

            return ['table' => 'attendance_deviation_reviews', 'id' => $id];
        },
        'financial_reports.store_id' => static function (Store $store): array {
            $model = FinancialReport::factory()->forStore($store)->forMonth(2025, 1)->create();

            return ['table' => 'financial_reports', 'id' => $model->getKey()];
        },
        'financial_recurring_expenses.store_id' => static function (Store $store): array {
            $model = FinancialRecurringExpense::factory()->forStore($store)->create();

            return ['table' => 'financial_recurring_expenses', 'id' => $model->getKey()];
        },
        'payroll_reports.store_id' => static function (Store $store) use ($admin): array {
            $id = DB::table('payroll_reports')->insertGetId([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'year' => 2025,
                'month' => 1,
                'status' => 'open',
                'created_at' => \now(),
                'updated_at' => \now(),
            ]);

            return ['table' => 'payroll_reports', 'id' => $id];
        },
        'noticeboard_cards.store_id' => static function (Store $store) use ($admin): array {
            $model = NoticeboardCard::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'created_by_user_id' => $admin->getKey(),
                'updated_by_user_id' => $admin->getKey(),
            ]);

            return ['table' => 'noticeboard_cards', 'id' => $model->getKey()];
        },
        'gift_vouchers.redeemed_store_id' => static function (Store $store) use ($admin): array {
            $model = GiftVoucher::factory()->create([
                'user_id' => $admin->getKey(),
                'status' => 'redeemed',
                'redeemed_at' => \now(),
                'redeemed_store_id' => $store->getKey(),
                'redeemed_by_user_id' => $admin->getKey(),
            ]);

            return ['table' => 'gift_vouchers', 'id' => $model->getKey()];
        },
        'gift_voucher_events.store_id' => static function (Store $store) use ($admin): array {
            $model = GiftVoucherEvent::factory()->create([
                'actor_user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
            ]);

            return ['table' => 'gift_voucher_events', 'id' => $model->getKey()];
        },
        'bank_statements.store_id' => static function (Store $store): array {
            $model = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed']);

            return ['table' => 'bank_statements', 'id' => $model->getKey()];
        },
        'shift_requests.store_id' => static function (Store $store) use ($admin): array {
            $model = ShiftRequest::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'worker_id' => Worker::factory()->create(['user_id' => $admin->getKey()])->getKey(),
                'date' => CarbonImmutable::today()->subDay()->toDateString(),
            ]);

            return ['table' => 'shift_requests', 'id' => $model->getKey()];
        },
        'shift_request_month_locks.store_id' => static function (Store $store) use ($admin): array {
            $model = ShiftRequestMonthLock::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'locked_by_user_id' => $admin->getKey(),
            ]);

            return ['table' => 'shift_request_month_locks', 'id' => $model->getKey()];
        },
        'shift_share_links.store_id' => static function (Store $store) use ($admin): array {
            $model = ShiftShareLink::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey()]);

            return ['table' => 'shift_share_links', 'id' => $model->getKey()];
        },
        'assistant_action_audits.store_id' => static function (Store $store) use ($admin): array {
            $model = AssistantActionAudit::factory()->create([
                'actor_user_id' => $admin->getKey(),
                'actor_email' => $admin->getEmail(),
                'store_id' => $store->getKey(),
                'store_name' => $store->getName(),
            ]);

            return ['table' => 'assistant_action_audits', 'id' => $model->getKey()];
        },
        'checklist_template_tasks.store_id' => static function (Store $store) use ($admin): array {
            $model = ChecklistTemplateTask::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
            ]);

            return ['table' => 'checklist_template_tasks', 'id' => $model->getKey()];
        },
        'checklist_days.store_id' => static function (Store $store) use ($admin): array {
            $model = ChecklistDay::factory()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'date' => CarbonImmutable::today()->subDay()->toDateString(),
            ]);

            return ['table' => 'checklist_days', 'id' => $model->getKey()];
        },
    ];

    foreach ($references as $family => $createReference) {
        $store = Store::factory()->create([
            'user_id' => $admin->getKey(),
            'is_warehouse' => false,
            'status' => StoreStatusEnum::ACTIVE->value,
        ]);
        $reference = $createReference($store);

        \expect((new StoreManagementService())->deleteStore($admin, $store))->toBe(RemovalOutcomeEnum::ARCHIVED, $family)
            ->and($store->refresh()->getStatus())->toBe(StoreStatusEnum::INACTIVE, $family)
            ->and(DB::table($reference['table'])->where('id', $reference['id'])->exists())->toBeTrue($family);
    }
});

\test('a pristine store created through the application is hard deleted with untouched checklist scaffolding', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = (new StoreManagementService())->createStore(
        $admin,
        'Pristine retail',
        null,
        StoreStatusEnum::ACTIVE->value,
        null,
        null,
        false,
    );

    \expect(ChecklistTemplateTask::query()->where('store_id', $store->getKey())->exists())->toBeTrue()
        ->and(ChecklistDay::query()->where('store_id', $store->getKey())->exists())->toBeTrue()
        ->and((new StoreManagementService())->deleteStore($admin, $store))->toBe(RemovalOutcomeEnum::DELETED)
        ->and(Store::query()->whereKey($store->getKey())->exists())->toBeFalse()
        ->and(ChecklistTemplateTask::query()->where('store_id', $store->getKey())->exists())->toBeFalse()
        ->and(ChecklistDay::query()->where('store_id', $store->getKey())->exists())->toBeFalse();
});

\test('an inactive store initializes prospective checklist data only when activated', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = (new StoreManagementService())->createStore(
        $admin,
        'Future retail',
        null,
        StoreStatusEnum::INACTIVE->value,
        null,
        null,
        false,
    );

    \expect(ChecklistTemplateTask::query()->where('store_id', $store->getKey())->exists())->toBeFalse()
        ->and(ChecklistDay::query()->where('store_id', $store->getKey())->exists())->toBeFalse();

    (new StoreManagementService())->updateStore(
        $admin,
        $store,
        $store->getName(),
        $store->getAddress(),
        StoreStatusEnum::ACTIVE->value,
        $store->getNotes(),
        $store->getSlackChannel(),
        false,
    );

    \expect(ChecklistTemplateTask::query()->where('store_id', $store->getKey())->exists())->toBeTrue()
        ->and(ChecklistDay::query()->where('store_id', $store->getKey())->exists())->toBeTrue();
});
