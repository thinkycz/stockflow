<?php

declare(strict_types=1);

use App\Domain\BankStatements\BankStatementService;
use App\Domain\Finance\FinancialReportService;
use App\Domain\Noticeboard\NoticeboardCardService;
use App\Domain\OperationalActivity\DailyOperationalDigestBuilder;
use App\Domain\Payroll\PayrollReportService;
use App\Domain\Workforce\AttendanceCorrectionService;
use App\Domain\Workforce\ShiftRequestService;
use App\Domain\Workforce\WorkforceManagementService;
use App\Enums\FilesystemDiskEnum;
use App\Enums\FinancialDirectionEnum;
use App\Enums\FinancialSourceTypeEnum;
use App\Enums\OperationalActivityTypeEnum;
use App\Enums\PayrollAdjustmentTypeEnum;
use App\Models\BankStatement;
use App\Models\OperationalActivity;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\ShiftRequest;
use App\Models\Store;
use App\Models\Worker;
use App\Notifications\OperationalActivitySlackNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thinkycz\LaravelCore\Support\Config;

\beforeEach(function (): void {
    Notification::fake();
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', 'xoxb-test');
    [$this->actor] = \createIsolatedUserWithWarehouse();
    $this->store = Store::factory()->create(['user_id' => $this->actor->getKey(), 'slack_channel' => '#operations']);
    $this->worker = Worker::factory()->create(['user_id' => $this->actor->getKey()]);
});

/**
 * Verify the latest immutable activity, real Slack payload and store routing.
 *
 * @param array<string, string> $facts
 */
function assertBusinessSlackActivity(OperationalActivityTypeEnum $type, Store $store, array $facts = []): void
{
    $activity = OperationalActivity::query()->latest('id')->firstOrFail();
    \expect($activity->getType())->toBe($type)
        ->and($activity->getCompanyUserId())->toBe($store->getUserId())
        ->and($activity->getStoreContexts()[0]['store_id'])->toBe($store->getKey())
        ->and($activity->getUrl())->toStartWith('http');
    foreach ($facts as $key => $value) {
        \expect($activity->getFacts()[$key] ?? null)->toBe($value);
    }
    Notification::assertSentOnDemand(
        OperationalActivitySlackNotification::class,
        function (OperationalActivitySlackNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($type): bool {
            $payload = $notification->toSlack($notifiable)->toArray();

            return $channels === ['slack'] && $notifiable->routeNotificationFor('slack') === '#operations' &&
                $payload['blocks'][0]['text']['text'] === \__($type->translationKey(), [], 'cs');
        },
    );
    $encoded = \json_encode($activity->getFacts(), \JSON_THROW_ON_ERROR);
    \expect($encoded)->not->toContain('private-note', 'hourly_rate', 'account_number', 'body_html');
}

\test('manual income and expenses notify creation changes and deletion but not unchanged saves', function (FinancialDirectionEnum $direction): void {
    $service = new FinancialReportService();
    $row = $service->createManualRow($this->actor, $this->store, 2026, 7, $direction, 'Entry', '2026-07-15', 100, 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::FINANCIAL_ROW_CREATED, $this->store, ['Slack amount' => '100,00 Kč']);
    $service->updateManualRow($this->actor, $this->store, 2026, 7, $row->getKey(), $direction, 'Entry', '2026-07-15', 150, 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::FINANCIAL_ROW_UPDATED, $this->store, ['Slack amount' => '150,00 Kč', 'Slack previous amount' => '100,00 Kč']);
    $service->updateManualRow($this->actor, $this->store, 2026, 7, $row->getKey(), $direction, 'Entry', '2026-07-15', 150, 'private-note');
    \expect(OperationalActivity::query()->count())->toBe(2);
    $service->deleteManualRow($this->actor, $this->store, 2026, 7, $row->getKey());
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::FINANCIAL_ROW_DELETED, $this->store, ['Slack amount' => '150,00 Kč']);
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 3);
})->with([FinancialDirectionEnum::INCOME, FinancialDirectionEnum::EXPENSE]);

\test('recurring expenses and overrides retain effective dates and before after amounts', function (): void {
    $service = new FinancialReportService();
    $expense = $service->createRecurringExpense($this->actor, $this->store, 2026, 7, 'Rent', 100, 15, 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::RECURRING_EXPENSE_CREATED, $this->store, ['Slack effective month' => '2026-07']);
    $service->changeRecurringExpense($this->actor, $this->store, $expense->getKey(), 2026, 8, 'Rent', 200, 20, 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::RECURRING_EXPENSE_UPDATED, $this->store, ['Slack previous amount' => '100,00 Kč', 'Slack amount' => '200,00 Kč']);
    $service->changeRecurringExpense($this->actor, $this->store, $expense->getKey(), 2026, 8, 'Rent', 200, 20, 'private-note');
    \expect(OperationalActivity::query()->count())->toBe(2);
    $service->setOverride($this->actor, $this->store, 2026, 8, FinancialSourceTypeEnum::RECURRING_EXPENSE, (string) $expense->getKey(), 175);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::FINANCIAL_OVERRIDE_SET, $this->store, ['Slack previous amount' => '200,00 Kč', 'Slack amount' => '175,00 Kč']);
    $service->setOverride($this->actor, $this->store, 2026, 8, FinancialSourceTypeEnum::RECURRING_EXPENSE, (string) $expense->getKey(), 175);
    \expect(OperationalActivity::query()->count())->toBe(3);
    $service->resetOverride($this->actor, $this->store, 2026, 8, FinancialSourceTypeEnum::RECURRING_EXPENSE, (string) $expense->getKey());
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::FINANCIAL_OVERRIDE_RESET, $this->store, ['Slack previous amount' => '175,00 Kč', 'Slack amount' => '200,00 Kč']);
    $service->resetOverride($this->actor, $this->store, 2026, 8, FinancialSourceTypeEnum::RECURRING_EXPENSE, (string) $expense->getKey());
    $service->terminateRecurringExpense($this->actor, $this->store, $expense->getKey(), 2026, 9);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::RECURRING_EXPENSE_TERMINATED, $this->store, ['Slack effective month' => '2026-09']);
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 5);
});

\test('payroll changes identify workers without exposing their wages or adjustment amounts', function (): void {
    $service = new PayrollReportService();
    $service->addWorker($this->actor, $this->store, 2026, 7, $this->worker);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::PAYROLL_WORKER_ADDED, $this->store);
    $service->upsertWageOverride($this->actor, $this->store, 2026, 7, $this->worker, 8, 239.75);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::PAYROLL_WAGE_OVERRIDE_SET, $this->store);
    $service->upsertWageOverride($this->actor, $this->store, 2026, 7, $this->worker, 8, 239.75);
    \expect(OperationalActivity::query()->count())->toBe(2);
    $adjustment = $service->createAdjustment($this->actor, $this->store, 2026, 7, $this->worker, PayrollAdjustmentTypeEnum::TIP, 47.81, 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::PAYROLL_ADJUSTMENT_CREATED, $this->store);
    $service->updateAdjustment($this->actor, $this->store, 2026, 7, $adjustment->getKey(), PayrollAdjustmentTypeEnum::TIP, 48.81, 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::PAYROLL_ADJUSTMENT_UPDATED, $this->store);
    $service->updateAdjustment($this->actor, $this->store, 2026, 7, $adjustment->getKey(), PayrollAdjustmentTypeEnum::TIP, 48.81, 'private-note');
    \expect(OperationalActivity::query()->count())->toBe(4);
    $service->deleteAdjustment($this->actor, $this->store, 2026, 7, $adjustment->getKey());
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::PAYROLL_ADJUSTMENT_DELETED, $this->store);
    $service->deleteWageOverride($this->actor, $this->store, 2026, 7, $this->worker->getKey());
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::PAYROLL_WAGE_OVERRIDE_RESET, $this->store);
    $service->removeWorker($this->actor, $this->store, 2026, 7, $this->worker->getKey());
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::PAYROLL_WORKER_REMOVED, $this->store);
    foreach (OperationalActivity::query()->get() as $activity) {
        \expect($activity->getFacts())->toHaveKeys(['Slack worker', 'Slack report month'])
            ->and(\json_encode($activity->getFacts()))->not->toContain('239.75', '47.81', '48.81', 'private-note');
    }
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 7);
});

\test('tip distributions and copying manual rows each create one aggregate activity', function (): void {
    $payroll = new PayrollReportService();
    $payroll->upsertWageOverride($this->actor, $this->store, 2026, 7, $this->worker, 8, 200);
    $other = Worker::factory()->create(['user_id' => $this->actor->getKey()]);
    $payroll->upsertWageOverride($this->actor, $this->store, 2026, 7, $other, 8, 200);
    $payroll->distributeTips($this->actor, $this->store, 2026, 7, 100);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::PAYROLL_TIPS_DISTRIBUTED, $this->store, ['Slack affected count' => '2', 'Slack payroll tips' => '100,00 Kč']);
    $finance = new FinancialReportService();
    foreach (['Rent', 'Supplies'] as $label) {
        $finance->createManualRow($this->actor, $this->store, 2026, 7, FinancialDirectionEnum::EXPENSE, $label, '2026-07-31', 100, null);
    }
    $finance->copyPreviousManualRows($this->actor, $this->store, 2026, 8);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::FINANCIAL_ROWS_COPIED, $this->store, ['Slack affected count' => '2']);
    $finance->copyPreviousManualRows($this->actor, $this->store, 2026, 8);
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 6);
});

\test('shift creation edits deletion approval and month locking notify exactly once', function (): void {
    $service = new WorkforceManagementService();
    $shift = $service->createShift($this->actor, $this->store, $this->worker, '2026-11-15', '08:00', '16:00', false);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::SHIFT_CREATED, $this->store);
    $service->updateShift($this->actor, $this->store, $shift, $this->worker, '2026-11-15', '09:00', '16:00', false);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::SHIFT_UPDATED, $this->store, ['Slack shift time' => '09:00–16:00']);
    $service->updateShift($this->actor, $this->store, $shift, $this->worker, '2026-11-15', '09:00', '16:00', false);
    $service->deleteShift($this->actor, $this->store, $shift);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::SHIFT_DELETED, $this->store);
    $preset = ShiftPreset::factory()->create(['user_id' => $this->actor->getKey(), 'store_id' => $this->store->getKey(), 'start_time' => '08:00', 'end_time' => '16:00']);
    $service->quickAddShift($this->actor, $this->store, $this->worker, $preset, '2026-11-16', false);
    $service->quickAddShift($this->actor, $this->store, $this->worker, $preset, '2026-11-16', false);
    \expect(OperationalActivity::query()->count())->toBe(4);
    $requests = new ShiftRequestService();
    $request = ShiftRequest::factory()->create(['user_id' => $this->actor->getKey(), 'store_id' => $this->store->getKey(), 'worker_id' => $this->worker->getKey(), 'date' => '2026-11-17']);
    $requests->approve($this->actor, $this->store, $request->getKey(), '08:00', '16:00', false);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::SHIFT_REQUEST_APPROVED, $this->store);
    $future = CarbonImmutable::now()->addMonths(2);
    $requests->setLocked($this->actor, $this->store, $future->year, $future->month, true);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::SHIFT_REQUESTS_LOCKED, $this->store);
    $requests->setLocked($this->actor, $this->store, $future->year, $future->month, true);
    $requests->setLocked($this->actor, $this->store, $future->year, $future->month, false);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::SHIFT_REQUESTS_UNLOCKED, $this->store);
    $requests->setLocked($this->actor, $this->store, $future->year, $future->month, false);
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 7);
});

\test('noticeboard meaningful changes notify but unchanged and cosmetic saves stay silent', function (): void {
    $service = new NoticeboardCardService();
    $card = $service->create($this->actor, $this->store, '<p>Announcement</p>', 'information', 'yellow', 'medium', null, null);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::NOTICEBOARD_CREATED, $this->store, ['Slack announcement' => 'Announcement']);
    $card = $service->update($card, $this->actor, '<p>Announcement</p>', 'information', 'blue', 'medium', null, null, false, $card->getLockVersion());
    \expect(OperationalActivity::query()->count())->toBe(1);
    $card = $service->update($card, $this->actor, '<p>Changed announcement</p>', 'information', 'blue', 'medium', null, null, false, $card->getLockVersion());
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::NOTICEBOARD_UPDATED, $this->store);
    $service->trash($card, $this->actor);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::NOTICEBOARD_TRASHED, $this->store);
    $service->restore($card, $this->actor);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::NOTICEBOARD_RESTORED, $this->store);
    $service->restore($card, $this->actor);
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 4);
});

\test('bank statement lifecycle uses safe identifiers and surviving deletion links', function (): void {
    Storage::fake(FilesystemDiskEnum::Private->value);
    $bank = BankStatement::factory()->forStore($this->store)->create(['status' => 'review', 'parse_warnings' => []]);
    $service = new BankStatementService();
    $service->confirm($bank, $this->actor);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::BANK_STATEMENT_CONFIRMED, $this->store);
    $service->reopen($bank, $this->actor);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::BANK_STATEMENT_REOPENED, $this->store);
    $service->delete($bank, $this->actor);
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::BANK_STATEMENT_DELETED, $this->store);
    \expect(OperationalActivity::query()->latest('id')->firstOrFail()->getUrl())->toContain('/bank-statements?store_id=');
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 3);
});

\test('attendance restore and manual and batch matching notify without correction reasons', function (): void {
    $service = new AttendanceCorrectionService();
    $start = CarbonImmutable::parse('2026-07-20 06:00:00 UTC');
    $end = $start->addHours(8);
    $session = $service->create($this->actor, $this->store, $this->worker, $start, $end, [], 'private-note');
    $service->update($this->actor, $session, $this->worker, $start, $end, [], 'private-note');
    $service->void($this->actor, $session, 'private-note');
    $service->void($this->actor, $session, 'private-note');
    $service->restore($this->actor, $session, 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::ATTENDANCE_CORRECTION_RESTORED, $this->store);
    $service->restore($this->actor, $session, 'private-note');
    $shift = Shift::factory()->create(['user_id' => $this->actor->getKey(), 'store_id' => $this->store->getKey(), 'worker_id' => $this->worker->getKey(), 'date' => '2026-07-20', 'start_time' => '08:00', 'end_time' => '16:00']);
    $service->matchShift($this->actor, $session, $shift->getKey(), 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::ATTENDANCE_SHIFT_MATCHED, $this->store);
    $service->matchShift($this->actor, $session, $shift->getKey(), 'private-note');
    $session->refresh()->update(['shift_id' => null]);
    $service->matchReport($this->actor, $this->store, '2026-07', null, 'private-note');
    \assertBusinessSlackActivity(OperationalActivityTypeEnum::ATTENDANCE_REPORT_MATCHED, $this->store, ['Slack affected count' => '1']);
    $service->matchReport($this->actor, $this->store, '2026-07', null, 'private-note');
    Notification::assertSentOnDemandTimes(OperationalActivitySlackNotification::class, 5);
});

\test('financial rollback and foreign store attempts do not publish activities', function (): void {
    $service = new FinancialReportService();
    try {
        DB::transaction(function () use ($service): void {
            $service->createManualRow($this->actor, $this->store, 2026, 7, FinancialDirectionEnum::INCOME, 'Rollback', '2026-07-01', 100, null);
            Notification::assertNothingSent();
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $exception) {
        \expect($exception->getMessage())->toBe('rollback');
    }
    $foreign = Store::factory()->create();
    try {
        $service->createManualRow($this->actor, $foreign, 2026, 7, FinancialDirectionEnum::INCOME, 'Forbidden', '2026-07-01', 100, null);
        $this->fail('Expected authorization failure');
    } catch (HttpException $exception) {
        \expect($exception->getStatusCode())->toBe(404);
    }
    \expect(OperationalActivity::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

\test('new financial activity is journaled without a token and included in the daily digest', function (): void {
    Config::inject()->assign('services.slack.notifications.bot_user_oauth_token', null);
    (new FinancialReportService())->createManualRow($this->actor, $this->store, 2026, 7, FinancialDirectionEnum::INCOME, 'Sales', '2026-07-01', 100, 'private-note');
    Notification::assertNothingSent();
    $digest = (new DailyOperationalDigestBuilder())->build($this->actor, CarbonImmutable::now('Europe/Prague')->startOfDay());
    $encoded = \json_encode($digest, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    \expect($digest['activity_count'])->toBe(1)
        ->and($encoded)->toContain('příjem nebo výdaj přidán', '100,00 Kč')->not->toContain('private-note');
});
