<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Attendance\AttendanceActionController;
use App\Http\Controllers\Web\Attendance\AttendanceCorrectionController;
use App\Http\Controllers\Web\Attendance\AttendanceDeviationReviewController;
use App\Http\Controllers\Web\Attendance\AttendanceIndexController;
use App\Http\Controllers\Web\Attendance\AttendancePrintController;
use App\Http\Controllers\Web\Attendance\AttendanceReportController;
use App\Http\Controllers\Web\Auth\EmailVerificationConfirmController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\LogoutController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\VerifyEmailController;
use App\Http\Controllers\Web\Checklist\ChecklistDayExcuseController;
use App\Http\Controllers\Web\Checklist\ChecklistIndexController;
use App\Http\Controllers\Web\Checklist\ChecklistItemController;
use App\Http\Controllers\Web\Checklist\ChecklistTemplateController;
use App\Http\Controllers\Web\Dashboard\DashboardController;
use App\Http\Controllers\Web\IncomeExpense\IncomeExpenseIndexController;
use App\Http\Controllers\Web\IncomeExpense\IncomeExpenseLifecycleController;
use App\Http\Controllers\Web\IncomeExpense\IncomeExpenseManualRowController;
use App\Http\Controllers\Web\IncomeExpense\IncomeExpenseOverrideController;
use App\Http\Controllers\Web\IncomeExpense\IncomeExpenseRecurringExpenseController;
use App\Http\Controllers\Web\InventoryCount\InventoryCountHistoryController;
use App\Http\Controllers\Web\InventoryCount\InventoryCountIndexController;
use App\Http\Controllers\Web\InventoryCount\InventoryCountShowController;
use App\Http\Controllers\Web\InventoryCount\InventoryCountUpdateController;
use App\Http\Controllers\Web\InventoryCount\InventoryDraftCancelController;
use App\Http\Controllers\Web\InventoryCount\InventoryDraftCloseController;
use App\Http\Controllers\Web\InventoryCount\InventoryDraftRowController;
use App\Http\Controllers\Web\InventoryCount\InventoryDraftStartController;
use App\Http\Controllers\Web\Item\ItemCreateController;
use App\Http\Controllers\Web\Item\ItemDestroyController;
use App\Http\Controllers\Web\Item\ItemEditController;
use App\Http\Controllers\Web\Item\ItemIndexController;
use App\Http\Controllers\Web\Item\ItemSearchController;
use App\Http\Controllers\Web\Item\ItemShowController;
use App\Http\Controllers\Web\Noticeboard\NoticeboardCardController;
use App\Http\Controllers\Web\Payroll\PayrollAdjustmentController;
use App\Http\Controllers\Web\Payroll\PayrollIndexController;
use App\Http\Controllers\Web\Payroll\PayrollLifecycleController;
use App\Http\Controllers\Web\Payroll\PayrollPrintController;
use App\Http\Controllers\Web\Payroll\PayrollShowController;
use App\Http\Controllers\Web\Payroll\PayrollWageOverrideController;
use App\Http\Controllers\Web\Payroll\PayrollWorkerController;
use App\Http\Controllers\Web\Report\ReportController;
use App\Http\Controllers\Web\Report\StatisticsController;
use App\Http\Controllers\Web\Settings\SettingsController;
use App\Http\Controllers\Web\Shift\SharedShiftIndexController;
use App\Http\Controllers\Web\Shift\ShiftDestroyController;
use App\Http\Controllers\Web\Shift\ShiftIndexController;
use App\Http\Controllers\Web\Shift\ShiftQuickAddController;
use App\Http\Controllers\Web\Shift\ShiftShareController;
use App\Http\Controllers\Web\Shift\ShiftStoreController;
use App\Http\Controllers\Web\Shift\ShiftUpdateController;
use App\Http\Controllers\Web\ShiftPreset\ShiftPresetDestroyController;
use App\Http\Controllers\Web\ShiftPreset\ShiftPresetStoreController;
use App\Http\Controllers\Web\ShiftPreset\ShiftPresetUpdateController;
use App\Http\Controllers\Web\Statement\StatementClearController;
use App\Http\Controllers\Web\Statement\StatementHistoryController;
use App\Http\Controllers\Web\Statement\StatementIndexController;
use App\Http\Controllers\Web\Statement\StatementTodayUpdateController;
use App\Http\Controllers\Web\Statement\StatementUpdateController;
use App\Http\Controllers\Web\Statement\StatementVersionRestoreController;
use App\Http\Controllers\Web\Statement\StatementVersionShowController;
use App\Http\Controllers\Web\StockMovement\StockMovementCreateController;
use App\Http\Controllers\Web\StockMovement\StockMovementIndexController;
use App\Http\Controllers\Web\StockMovement\StockMovementReverseController;
use App\Http\Controllers\Web\StockMovement\StockMovementShowController;
use App\Http\Controllers\Web\Store\StoreCreateController;
use App\Http\Controllers\Web\Store\StoreDestroyController;
use App\Http\Controllers\Web\Store\StoreEditController;
use App\Http\Controllers\Web\Store\StoreIndexController;
use App\Http\Controllers\Web\Store\StoreShowController;
use App\Http\Controllers\Web\Store\StoreSwitchController;
use App\Http\Controllers\Web\User\UserCreateController;
use App\Http\Controllers\Web\User\UserDestroyController;
use App\Http\Controllers\Web\User\UserEditController;
use App\Http\Controllers\Web\User\UserIndexController;
use App\Http\Controllers\Web\Worker\WorkerCreateController;
use App\Http\Controllers\Web\Worker\WorkerDestroyController;
use App\Http\Controllers\Web\Worker\WorkerEditController;
use App\Http\Controllers\Web\Worker\WorkerIndexController;
use App\Http\Middleware\EnsureInertiaUserIsAuthenticated;
use App\Models\User;
use Illuminate\Routing\Router;
use Thinkycz\LaravelCore\Support\Resolver;

Resolver::resolveRouteRegistrar()->get('/', static function () {
    if (User::auth() instanceof User) {
        return Resolver::resolveRedirector()->route('dashboard');
    }

    return Resolver::resolveRedirector()->route('login.show');
})->name('home');

Resolver::resolveRouteRegistrar()
    ->middleware('guest:users')
    ->group(static function (Router $router): void {
        $router->get('login', [LoginController::class, 'create'])->name('login.show');
        $router->post('login', [LoginController::class, 'store'])->name('login.store');
        $router->get('forgot-password', [ForgotPasswordController::class, 'create'])->name('forgot-password.show');
        $router->post('forgot-password', [ForgotPasswordController::class, 'store'])->name('forgot-password.store');
        $router->get('reset-password', [ResetPasswordController::class, 'create'])->name('reset-password.show');
        $router->post('reset-password', [ResetPasswordController::class, 'store'])->name('reset-password.store');
    });

Resolver::resolveRouteRegistrar()->get('email/verify', EmailVerificationConfirmController::class)->name('email.verify');

Resolver::resolveRouteRegistrar()->get('public/shifts/{token}', SharedShiftIndexController::class)->name('public-shifts.index');

Resolver::resolveRouteRegistrar()
    ->middleware(EnsureInertiaUserIsAuthenticated::class)
    ->group(static function (Router $router): void {
        $router->post('logout', LogoutController::class)->name('logout');
        $router->get('dashboard', DashboardController::class)->name('dashboard');
        $router->post('noticeboard-cards', [NoticeboardCardController::class, 'store'])->name('noticeboard-cards.store');
        $router->put('noticeboard-cards/{noticeboardCard}', [NoticeboardCardController::class, 'update'])->whereNumber('noticeboardCard')->name('noticeboard-cards.update');
        $router->delete('noticeboard-cards/{noticeboardCard}', [NoticeboardCardController::class, 'destroy'])->whereNumber('noticeboardCard')->name('noticeboard-cards.destroy');
        $router->get('noticeboard-cards/{noticeboardCard}/image', [NoticeboardCardController::class, 'image'])->whereNumber('noticeboardCard')->name('noticeboard-cards.image');

        // Statements (admin + limited)
        $router->get('statements', StatementIndexController::class)->name('statements.index');
        $router->put('statements/{statement}', StatementUpdateController::class)->whereNumber('statement')->name('statements.update');
        $router->put('statements/{statement}/today', StatementTodayUpdateController::class)->whereNumber('statement')->name('statements.today.update');
        $router->post('statements/{statement}/clear', StatementClearController::class)->whereNumber('statement')->name('statements.clear');
        $router->get('statements/{statement}/history', StatementHistoryController::class)->whereNumber('statement')->name('statements.history');
        $router->get('statements/versions/{version}', StatementVersionShowController::class)->whereNumber('version')->name('statements.versions.show');
        $router->post('statements/versions/{version}/restore', StatementVersionRestoreController::class)->whereNumber('version')->name('statements.versions.restore');

        // Inventory counts (admin + limited)
        $router->get('inventory-counts', InventoryCountIndexController::class)->name('inventory-counts.index');
        $router->post('inventory-counts', InventoryCountUpdateController::class)->name('inventory-counts.update');
        $router->post('inventory-counts/drafts', InventoryDraftStartController::class)->name('inventory-counts.drafts.start');
        $router->put('inventory-counts/drafts/{session}/rows', InventoryDraftRowController::class)->whereNumber('session')->name('inventory-counts.drafts.rows.update');
        $router->post('inventory-counts/drafts/{session}/close', InventoryDraftCloseController::class)->whereNumber('session')->name('inventory-counts.drafts.close');
        $router->post('inventory-counts/drafts/{session}/cancel', InventoryDraftCancelController::class)->whereNumber('session')->name('inventory-counts.drafts.cancel');
        $router->get('inventory-counts/history', InventoryCountHistoryController::class)->name('inventory-counts.history');
        $router->get('inventory-counts/{session}', InventoryCountShowController::class)->whereNumber('session')->name('inventory-counts.show');

        // Manual consumption (admin + limited assigned-store users)
        $router->get('stock-movements/create', [StockMovementCreateController::class, 'create'])->name('stock-movements.create');
        $router->post('stock-movements', [StockMovementCreateController::class, 'store'])->name('stock-movements.store');
        $router->get('items/search', ItemSearchController::class)->name('items.search');

        // Shifts (admin + limited view)
        $router->get('shifts', ShiftIndexController::class)->name('shifts.index');
        $router->post('shifts/share', ShiftShareController::class)->name('shifts.share');

        // Attendance (admin + limited assigned-store users)
        $router->get('attendance', AttendanceIndexController::class)->name('attendance.index');
        $router->post('attendance/actions', AttendanceActionController::class)->name('attendance.actions.store');

        // Store checklists (admin + limited completion)
        $router->put('checklist-items/{checklistItem}', ChecklistItemController::class)->whereNumber('checklistItem')->name('checklist-items.update');

        // Settings
        $router->get('verify-email', [VerifyEmailController::class, 'create'])->name('verify-email.show');
        $router->post('verify-email', [VerifyEmailController::class, 'store'])->name('verify-email.store');
    });

Resolver::resolveRouteRegistrar()
    ->middleware([EnsureInertiaUserIsAuthenticated::class, 'admin'])
    ->group(static function (Router $router): void {
        // Settings
        $router->get('settings', [SettingsController::class, 'edit'])->name('settings.show');
        $router->post('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
        $router->post('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');

        // Noticeboard moderation
        $router->post('noticeboard-cards/{noticeboardCard}/restore', [NoticeboardCardController::class, 'restore'])->whereNumber('noticeboardCard')->name('noticeboard-cards.restore');

        // Monthly payroll
        $router->get('payroll', PayrollIndexController::class)->name('payroll.index');
        $router->get('payroll/workers/{worker}', PayrollShowController::class)->whereNumber('worker')->name('payroll.show');
        $router->get('payroll/print', PayrollPrintController::class)->name('payroll.print');
        $router->post('payroll/workers', [PayrollWorkerController::class, 'store'])->name('payroll.workers.store');
        $router->delete('payroll/workers/{worker}', [PayrollWorkerController::class, 'destroy'])->whereNumber('worker')->name('payroll.workers.destroy');
        $router->post('payroll/adjustments', [PayrollAdjustmentController::class, 'store'])->name('payroll.adjustments.store');
        $router->put('payroll/adjustments/{payrollAdjustment}', [PayrollAdjustmentController::class, 'update'])->whereNumber('payrollAdjustment')->name('payroll.adjustments.update');
        $router->delete('payroll/adjustments/{payrollAdjustment}', [PayrollAdjustmentController::class, 'destroy'])->whereNumber('payrollAdjustment')->name('payroll.adjustments.destroy');
        $router->put('payroll/wage-override', [PayrollWageOverrideController::class, 'update'])->name('payroll.wage-override.update');
        $router->delete('payroll/wage-override', [PayrollWageOverrideController::class, 'destroy'])->name('payroll.wage-override.destroy');
        $router->post('payroll/close', [PayrollLifecycleController::class, 'close'])->name('payroll.close');
        $router->post('payroll/reopen', [PayrollLifecycleController::class, 'reopen'])->name('payroll.reopen');

        // Monthly store income and expenses
        $router->get('income-expenses', IncomeExpenseIndexController::class)->name('income-expenses.index');
        $router->post('income-expenses/overrides', [IncomeExpenseOverrideController::class, 'store'])->name('income-expenses.overrides.store');
        $router->delete('income-expenses/overrides', [IncomeExpenseOverrideController::class, 'destroy'])->name('income-expenses.overrides.destroy');
        $router->post('income-expenses/manual-rows', [IncomeExpenseManualRowController::class, 'store'])->name('income-expenses.manual-rows.store');
        $router->put('income-expenses/manual-rows/{manualRow}', [IncomeExpenseManualRowController::class, 'update'])->whereNumber('manualRow')->name('income-expenses.manual-rows.update');
        $router->delete('income-expenses/manual-rows/{manualRow}', [IncomeExpenseManualRowController::class, 'destroy'])->whereNumber('manualRow')->name('income-expenses.manual-rows.destroy');
        $router->get('income-expenses/recurring-expenses', [IncomeExpenseRecurringExpenseController::class, 'index'])->name('income-expenses.recurring-expenses.index');
        $router->post('income-expenses/recurring-expenses', [IncomeExpenseRecurringExpenseController::class, 'store'])->name('income-expenses.recurring-expenses.store');
        $router->put('income-expenses/recurring-expenses/{recurringExpense}', [IncomeExpenseRecurringExpenseController::class, 'update'])->whereNumber('recurringExpense')->name('income-expenses.recurring-expenses.update');
        $router->post('income-expenses/recurring-expenses/{recurringExpense}/terminate', [IncomeExpenseRecurringExpenseController::class, 'terminate'])->whereNumber('recurringExpense')->name('income-expenses.recurring-expenses.terminate');
        $router->post('income-expenses/copy-previous', [IncomeExpenseLifecycleController::class, 'copyPrevious'])->name('income-expenses.copy-previous');
        $router->post('income-expenses/close', [IncomeExpenseLifecycleController::class, 'close'])->name('income-expenses.close');
        $router->post('income-expenses/reopen', [IncomeExpenseLifecycleController::class, 'reopen'])->name('income-expenses.reopen');
        $router->delete('noticeboard-cards/{noticeboardCard}/force', [NoticeboardCardController::class, 'forceDestroy'])->whereNumber('noticeboardCard')->name('noticeboard-cards.force-destroy');

        // Items
        $router->get('items', ItemIndexController::class)->name('items.index');
        $router->get('items/create', [ItemCreateController::class, 'create'])->name('items.create');
        $router->post('items', [ItemCreateController::class, 'store'])->name('items.store');
        $router->get('items/{item}', ItemShowController::class)->whereNumber('item')->name('items.show');
        $router->get('items/{item}/edit', [ItemEditController::class, 'edit'])->whereNumber('item')->name('items.edit');
        $router->put('items/{item}', [ItemEditController::class, 'update'])->whereNumber('item')->name('items.update');
        $router->delete('items/{item}', ItemDestroyController::class)->whereNumber('item')->name('items.destroy');

        // Stores
        $router->get('stores', StoreIndexController::class)->name('stores.index');
        $router->post('stores/switch', StoreSwitchController::class)->name('stores.switch');
        $router->get('stores/create', [StoreCreateController::class, 'create'])->name('stores.create');
        $router->post('stores', [StoreCreateController::class, 'store'])->name('stores.store');
        $router->get('stores/{store}', StoreShowController::class)->whereNumber('store')->name('stores.show');
        $router->get('stores/{store}/edit', [StoreEditController::class, 'edit'])->whereNumber('store')->name('stores.edit');
        $router->put('stores/{store}', [StoreEditController::class, 'update'])->whereNumber('store')->name('stores.update');
        $router->delete('stores/{store}', StoreDestroyController::class)->whereNumber('store')->name('stores.destroy');

        // Stock movements
        $router->get('stock-movements', StockMovementIndexController::class)->name('stock-movements.index');
        $router->get('stock-movements/{stockMovement}', StockMovementShowController::class)->whereNumber('stockMovement')->name('stock-movements.show');
        $router->post('stock-movements/{stockMovement}/reverse', StockMovementReverseController::class)->whereNumber('stockMovement')->name('stock-movements.reverse');

        // Reports
        $router->get('reports', ReportController::class)->name('reports.index');
        $router->get('reports/statistics', StatisticsController::class)->name('reports.statistics');

        // Users
        $router->get('users', UserIndexController::class)->name('users.index');
        $router->get('users/create', [UserCreateController::class, 'create'])->name('users.create');
        $router->post('users', [UserCreateController::class, 'store'])->name('users.store');
        $router->get('users/{user}/edit', [UserEditController::class, 'edit'])->whereNumber('user')->name('users.edit');
        $router->put('users/{user}', [UserEditController::class, 'update'])->whereNumber('user')->name('users.update');
        $router->delete('users/{user}', UserDestroyController::class)->whereNumber('user')->name('users.destroy');

        // Workers
        $router->get('workers', WorkerIndexController::class)->name('workers.index');
        $router->get('workers/create', [WorkerCreateController::class, 'create'])->name('workers.create');
        $router->post('workers', [WorkerCreateController::class, 'store'])->name('workers.store');
        $router->get('workers/{worker}/edit', [WorkerEditController::class, 'edit'])->whereNumber('worker')->name('workers.edit');
        $router->put('workers/{worker}', [WorkerEditController::class, 'update'])->whereNumber('worker')->name('workers.update');
        $router->delete('workers/{worker}', WorkerDestroyController::class)->whereNumber('worker')->name('workers.destroy');

        // Shifts (admin write)
        $router->post('shifts', ShiftStoreController::class)->name('shifts.store');
        $router->post('shifts/quick-add', ShiftQuickAddController::class)->name('shifts.quick-add');
        $router->put('shifts/{shift}', ShiftUpdateController::class)->whereNumber('shift')->name('shifts.update');
        $router->delete('shifts/{shift}', ShiftDestroyController::class)->whereNumber('shift')->name('shifts.destroy');

        // Shift presets (admin write)
        $router->post('shift-presets', ShiftPresetStoreController::class)->name('shift-presets.store');
        $router->put('shift-presets/{shiftPreset}', ShiftPresetUpdateController::class)->whereNumber('shiftPreset')->name('shift-presets.update');
        $router->delete('shift-presets/{shiftPreset}', ShiftPresetDestroyController::class)->whereNumber('shiftPreset')->name('shift-presets.destroy');

        // Attendance corrections and reports (admin only)
        $router->get('attendance/report', AttendanceReportController::class)->name('attendance.report');
        $router->get('attendance/print', AttendancePrintController::class)->name('attendance.print');
        $router->post('attendance/corrections', [AttendanceCorrectionController::class, 'store'])->name('attendance.corrections.store');
        $router->post('attendance/shifts/{shift}/deviation-reviews', AttendanceDeviationReviewController::class)->whereNumber('shift')->name('attendance.deviation-reviews.store');
        $router->put('attendance/sessions/{attendanceSession}', [AttendanceCorrectionController::class, 'update'])->whereNumber('attendanceSession')->name('attendance.sessions.update');
        $router->post('attendance/sessions/{attendanceSession}/void', [AttendanceCorrectionController::class, 'void'])->whereNumber('attendanceSession')->name('attendance.sessions.void');

        // Store checklist administration
        $router->get('checklists', ChecklistIndexController::class)->name('checklists.index');
        $router->put('checklists/templates', ChecklistTemplateController::class)->name('checklists.templates.update');
        $router->put('checklist-days/{checklistDay}/excuse', [ChecklistDayExcuseController::class, 'update'])->whereNumber('checklistDay')->name('checklist-days.excuse');
        $router->delete('checklist-days/{checklistDay}/excuse', [ChecklistDayExcuseController::class, 'destroy'])->whereNumber('checklistDay')->name('checklist-days.excuse.destroy');
    });
