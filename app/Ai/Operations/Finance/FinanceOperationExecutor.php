<?php

declare(strict_types=1);

namespace App\Ai\Operations\Finance;

use App\Ai\Operations\AssistantOperationExecutor;
use App\Enums\FinancialDirectionEnum;
use App\Enums\FinancialSourceTypeEnum;
use App\Enums\PayrollAdjustmentTypeEnum;
use App\Http\Validation\FinancialReportValidity;
use App\Http\Validation\GiftVoucherValidity;
use App\Http\Validation\PayrollReportValidity;
use App\Models\FinancialRecurringExpense;
use App\Models\FinancialReportManualRow;
use App\Models\GiftVoucher;
use App\Models\GiftVoucherSetting;
use App\Models\PayrollAdjustment;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\FinancialReportService;
use App\Services\GiftVoucherBrandingService;
use App\Services\GiftVoucherService;
use App\Services\PayrollReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

final class FinanceOperationExecutor implements AssistantOperationExecutor
{
    /**
     * Create the service-backed finance operation executor.
     */
    public function __construct(
        private readonly FinancialReportService $financial,
        private readonly PayrollReportService $payroll,
        private readonly GiftVoucherService $vouchers,
        private readonly GiftVoucherBrandingService $branding,
    ) {}

    /**
     * Validate the proposal and resolve its store and target without side effects.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function preview(string $identifier, User $actor, array $arguments): array
    {
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $storeId = Typer::parseNullableInt($arguments['store_id'] ?? null);
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $store = $storeId === null ? null : $this->store($actor, $storeId);
        $this->validate($identifier, $actor, $context, $values);
        $target = $this->target($identifier, $actor, $store, $targetId);

        return [
            'operation' => $identifier,
            'store' => $store === null ? null : ['id' => $store->getKey(), 'name' => $store->getName()],
            'target' => $targetId === null ? null : ['type' => $target, 'id' => (string) $targetId],
            'effects' => $this->effects($identifier),
            'sanitized_arguments' => ['context' => $context, 'values' => $values],
            'safe_editable_fields' => ['values_json'],
        ];
    }

    /**
     * Execute an approved finance operation through the same domain service as the human UI.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(string $identifier, User $actor, array $arguments): array
    {
        $context = $this->json($arguments, 'context_json');
        $values = $this->json($arguments, 'values_json');
        $storeId = Typer::parseNullableInt($arguments['store_id'] ?? null);
        $targetId = Typer::parseNullableInt($arguments['target_id'] ?? null);
        $store = $storeId === null ? null : $this->store($actor, $storeId);
        $this->validate($identifier, $actor, $context, $values);
        $this->target($identifier, $actor, $store, $targetId);
        $recordId = $this->run($identifier, $actor, $store, $targetId, $context, $values);

        return [
            'operation' => $identifier,
            'status' => 'succeeded',
            'record' => [
                'type' => $this->resultType($identifier),
                'id' => $recordId ?? $targetId,
                'store_id' => $store?->getKey(),
                'url' => $this->url($identifier, $store, $context),
            ],
        ];
    }

    /**
     * Execute one fixed allow-listed finance operation.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function run(string $identifier, User $actor, Store|null $store, int|null $targetId, array $context, array $values): int|null
    {
        $year = Typer::parseNullableInt($context['year'] ?? null);
        $month = Typer::parseNullableInt($context['month'] ?? null);
        $resolvedStore = $store instanceof Store ? $store : null;

        return match ($identifier) {
            'copy_previous_financial_rows' => $this->financial->copyPreviousManualRows($actor, Typer::assertInstance($resolvedStore, Store::class), Typer::assertInt($year), Typer::assertInt($month)),
            'create_financial_row' => $this->financial->createManualRow($actor, Typer::assertInstance($resolvedStore, Store::class), Typer::assertInt($year), Typer::assertInt($month), FinancialDirectionEnum::from(Typer::assertString($values['direction'] ?? null)), Typer::assertString($values['label'] ?? null), Typer::assertString($values['occurred_on'] ?? null), Typer::parseFloat($values['amount'] ?? null), Typer::parseNullableString($values['note'] ?? null))->getKey(),
            'create_recurring_expense' => $this->createRecurring($actor, Typer::assertInstance($resolvedStore, Store::class), $values),
            'update_recurring_expense' => $this->updateRecurring($actor, Typer::assertInstance($resolvedStore, Store::class), Typer::assertInt($targetId), $values),
            'terminate_recurring_expense' => $this->terminateRecurring($actor, Typer::assertInstance($resolvedStore, Store::class), Typer::assertInt($targetId), $values),
            'create_payroll_adjustment' => $this->payroll->createAdjustment($actor, Typer::assertInstance($resolvedStore, Store::class), Typer::assertInt($year), Typer::assertInt($month), $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null)), PayrollAdjustmentTypeEnum::from(Typer::assertString($values['type'] ?? null)), Typer::parseFloat($values['amount'] ?? null), Typer::assertString($values['reason'] ?? null))->getKey(),
            'issue_gift_vouchers' => $this->issueVouchers($actor, $values),
            'redeem_gift_voucher' => $this->vouchers->redeem($actor, Typer::assertInstance($resolvedStore, Store::class), $this->voucher($actor, Typer::assertInt($targetId)))->getKey(),
            'void_gift_voucher' => $this->vouchers->void($actor, $this->voucher($actor, Typer::assertInt($targetId)), Typer::assertString($values['reason'] ?? null))->getKey(),
            'reverse_gift_voucher_redemption' => $this->vouchers->reverseRedemption($actor, $this->voucher($actor, Typer::assertInt($targetId)), Typer::assertString($values['reason'] ?? null))->getKey(),
            default => $this->runVoid($identifier, $actor, $resolvedStore, $targetId, $context, $values, $year, $month),
        };
    }

    /**
     * Execute a finance service method whose application contract returns void.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function runVoid(string $identifier, User $actor, Store|null $store, int|null $targetId, array $context, array $values, int|null $year, int|null $month): null
    {
        $store = Typer::assertInstance($store, Store::class);

        match ($identifier) {
            'close_financial_report' => $this->financial->close($actor, $store, Typer::assertInt($year), Typer::assertInt($month)),
            'reopen_financial_report' => $this->financial->reopen($actor, $store, Typer::assertInt($year), Typer::assertInt($month)),
            'update_financial_row' => $this->financial->updateManualRow($actor, $store, Typer::assertInt($year), Typer::assertInt($month), Typer::assertInt($targetId), FinancialDirectionEnum::from(Typer::assertString($values['direction'] ?? null)), Typer::assertString($values['label'] ?? null), Typer::assertString($values['occurred_on'] ?? null), Typer::parseFloat($values['amount'] ?? null), Typer::parseNullableString($values['note'] ?? null)),
            'delete_financial_row' => $this->financial->deleteManualRow($actor, $store, Typer::assertInt($year), Typer::assertInt($month), Typer::assertInt($targetId)),
            'set_financial_override' => $this->financial->setOverride($actor, $store, Typer::assertInt($year), Typer::assertInt($month), FinancialSourceTypeEnum::from(Typer::assertString($context['source_type'] ?? null)), Typer::assertString($context['source_key'] ?? null), Typer::parseFloat($values['amount'] ?? null)),
            'reset_financial_override' => $this->financial->resetOverride($actor, $store, Typer::assertInt($year), Typer::assertInt($month), FinancialSourceTypeEnum::from(Typer::assertString($context['source_type'] ?? null)), Typer::assertString($context['source_key'] ?? null)),
            'close_payroll_report' => $this->payroll->close($actor, $store, Typer::assertInt($year), Typer::assertInt($month)),
            'reopen_payroll_report' => $this->payroll->reopen($actor, $store, Typer::assertInt($year), Typer::assertInt($month)),
            'add_payroll_worker' => $this->payroll->addWorker($actor, $store, Typer::assertInt($year), Typer::assertInt($month), $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null))),
            'remove_payroll_worker' => $this->payroll->removeWorker($actor, $store, Typer::assertInt($year), Typer::assertInt($month), Typer::parseInt($context['worker_id'] ?? null)),
            'set_payroll_wage_override' => $this->payroll->upsertWageOverride($actor, $store, Typer::assertInt($year), Typer::assertInt($month), $this->worker($actor, Typer::parseInt($context['worker_id'] ?? null)), Typer::parseFloat($values['hours'] ?? null), Typer::parseFloat($values['hourly_rate'] ?? null)),
            'reset_payroll_wage_override' => $this->payroll->deleteWageOverride($actor, $store, Typer::assertInt($year), Typer::assertInt($month), Typer::parseInt($context['worker_id'] ?? null)),
            'update_payroll_adjustment' => $this->payroll->updateAdjustment($actor, $store, Typer::assertInt($year), Typer::assertInt($month), Typer::assertInt($targetId), PayrollAdjustmentTypeEnum::from(Typer::assertString($values['type'] ?? null)), Typer::parseFloat($values['amount'] ?? null), Typer::assertString($values['reason'] ?? null)),
            'delete_payroll_adjustment' => $this->payroll->deleteAdjustment($actor, $store, Typer::assertInt($year), Typer::assertInt($month), Typer::assertInt($targetId)),
            'distribute_payroll_tips' => $this->payroll->distributeTips($actor, $store, Typer::assertInt($year), Typer::assertInt($month), Typer::parseFloat($values['amount'] ?? null)),
            'update_voucher_branding' => $this->branding->update($actor, Typer::assertString($values['public_name'] ?? null), Typer::parseNullableString($values['message'] ?? null), null, Typer::parseBool($values['remove_logo'] ?? false)),
            default => throw new InvalidArgumentException('Unknown finance operation.'),
        };

        return null;
    }

    /**
     * Validate the operation's public business values with the human form rules.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $values
     */
    private function validate(string $identifier, User $actor, array $context, array $values): void
    {
        $financial = FinancialReportValidity::inject();
        $payroll = PayrollReportValidity::inject();
        $voucher = GiftVoucherValidity::inject();
        $period = ['year' => $context['year'] ?? null, 'month' => $context['month'] ?? null];
        $payload = match (true) {
            \in_array($identifier, ['copy_previous_financial_rows', 'close_financial_report', 'reopen_financial_report', 'delete_financial_row', 'close_payroll_report', 'reopen_payroll_report', 'remove_payroll_worker', 'reset_payroll_wage_override', 'delete_payroll_adjustment'], true) => $period,
            \in_array($identifier, ['create_financial_row', 'update_financial_row'], true) => [...$period, ...$values],
            \in_array($identifier, ['set_financial_override', 'reset_financial_override'], true) => [...$period, 'source_type' => $context['source_type'] ?? null, 'source_key' => $context['source_key'] ?? null, ...$values],
            \in_array($identifier, ['close_payroll_report', 'reopen_payroll_report'], true) => $period,
            $identifier === 'distribute_payroll_tips' => [...$period, ...$values],
            \in_array($identifier, ['add_payroll_worker', 'remove_payroll_worker', 'reset_payroll_wage_override'], true) => [...$period, 'worker_id' => $context['worker_id'] ?? null],
            \in_array($identifier, ['set_payroll_wage_override', 'create_payroll_adjustment'], true) => [...$period, 'worker_id' => $context['worker_id'] ?? null, ...$values],
            $identifier === 'update_payroll_adjustment' => [...$period, 'worker_id' => $context['worker_id'] ?? $actor->getKey(), ...$values],
            default => $values,
        };
        $rules = match ($identifier) {
            'copy_previous_financial_rows', 'close_financial_report', 'reopen_financial_report' => ['year' => $financial->year()->required()->toArray(), 'month' => $financial->month()->required()->toArray()],
            'create_financial_row', 'update_financial_row' => ['year' => $financial->year()->required()->toArray(), 'month' => $financial->month()->required()->toArray(), 'direction' => $financial->direction()->required()->toArray(), 'label' => $financial->label()->required()->toArray(), 'occurred_on' => $financial->occurredOn()->required()->toArray(), 'amount' => $financial->amount()->required()->toArray(), 'note' => $financial->note()->nullable()->toArray()],
            'delete_financial_row' => ['year' => $financial->year()->required()->toArray(), 'month' => $financial->month()->required()->toArray()],
            'set_financial_override' => ['year' => $financial->year()->required()->toArray(), 'month' => $financial->month()->required()->toArray(), 'source_type' => $financial->sourceType()->required()->toArray(), 'source_key' => $financial->sourceKey()->required()->toArray(), 'amount' => $financial->amount()->required()->toArray()],
            'reset_financial_override' => ['year' => $financial->year()->required()->toArray(), 'month' => $financial->month()->required()->toArray(), 'source_type' => $financial->sourceType()->required()->toArray(), 'source_key' => $financial->sourceKey()->required()->toArray()],
            'create_recurring_expense', 'update_recurring_expense' => ['effective_period' => $financial->period()->required()->toArray(), 'label' => $financial->label()->required()->toArray(), 'amount' => $financial->amount()->required()->toArray(), 'due_day' => $financial->dueDay()->required()->toArray(), 'note' => $financial->note()->nullable()->toArray()],
            'terminate_recurring_expense' => ['ends_before_period' => $financial->period()->required()->toArray()],
            'close_payroll_report', 'reopen_payroll_report', 'remove_payroll_worker', 'reset_payroll_wage_override', 'delete_payroll_adjustment' => ['year' => $payroll->year()->required()->toArray(), 'month' => $payroll->month()->required()->toArray()],
            'add_payroll_worker' => ['year' => $payroll->year()->required()->toArray(), 'month' => $payroll->month()->required()->toArray(), 'worker_id' => $payroll->workerId()->required()->toArray()],
            'set_payroll_wage_override' => ['year' => $payroll->year()->required()->toArray(), 'month' => $payroll->month()->required()->toArray(), 'worker_id' => $payroll->workerId()->required()->toArray(), 'hours' => $payroll->hours()->required()->toArray(), 'hourly_rate' => $payroll->hourlyRate()->required()->toArray()],
            'distribute_payroll_tips' => ['year' => $payroll->year()->required()->toArray(), 'month' => $payroll->month()->required()->toArray(), 'amount' => $payroll->amount()->required()->toArray()],
            'create_payroll_adjustment', 'update_payroll_adjustment' => ['year' => $payroll->year()->required()->toArray(), 'month' => $payroll->month()->required()->toArray(), 'worker_id' => $payroll->workerId()->required()->toArray(), 'type' => $payroll->type()->required()->toArray(), 'amount' => $payroll->amount()->required()->toArray(), 'reason' => $payroll->reason()->required()->toArray()],
            'issue_gift_vouchers' => ['quantity' => $voucher->quantity()->required()->toArray(), 'amount' => $voucher->amount()->required()->toArray(), 'expires_on' => $voucher->expiresOn()->nullable()->toArray()],
            'update_voucher_branding' => ['public_name' => $voucher->publicName()->required()->toArray(), 'message' => $voucher->message()->nullable()->toArray(), 'remove_logo' => $voucher->removeLogo()->nullable()->toArray()],
            'redeem_gift_voucher' => [],
            'void_gift_voucher', 'reverse_gift_voucher_redemption' => ['reason' => $voucher->reason()->required()->toArray()],
            default => throw new InvalidArgumentException('Unknown finance operation.'),
        };
        $validated = Resolver::resolveValidatorFactory()->make($payload, $rules)->validate();

        if (\in_array($identifier, ['create_financial_row', 'update_financial_row'], true)) {
            $date = CarbonImmutable::parse(Typer::assertString($validated['occurred_on'] ?? null));

            if ($date->year !== Typer::parseInt($validated['year'] ?? null) || $date->month !== Typer::parseInt($validated['month'] ?? null)) {
                Thrower::default()->message('occurred_on', \__('The row date must be inside the selected month.'))->throw();
            }
        }
    }

    /**
     * Resolve and authorize an operation target.
     */
    private function target(string $identifier, User $actor, Store|null $store, int|null $targetId): string|null
    {
        if ($targetId === null) {
            return null;
        }

        if (\in_array($identifier, ['update_financial_row', 'delete_financial_row'], true)) {
            FinancialReportManualRow::query()->whereHas('financialReport', static function (Builder $query) use ($actor, $store): void {
                $query->where('user_id', $actor->getKey())->where('store_id', $store?->getKey());
            })->whereKey($targetId)->firstOrFail();

            return 'financial_report_manual_row';
        }

        if (\in_array($identifier, ['update_recurring_expense', 'terminate_recurring_expense'], true)) {
            FinancialRecurringExpense::query()->where('user_id', $actor->getKey())->where('store_id', $store?->getKey())->whereKey($targetId)->firstOrFail();

            return 'financial_recurring_expense';
        }

        if (\in_array($identifier, ['update_payroll_adjustment', 'delete_payroll_adjustment'], true)) {
            PayrollAdjustment::query()->whereHas('payrollReport', static function (Builder $query) use ($actor, $store): void {
                $query->where('user_id', $actor->getKey())->where('store_id', $store?->getKey());
            })->whereKey($targetId)->firstOrFail();

            return 'payroll_adjustment';
        }

        if (\in_array($identifier, ['redeem_gift_voucher', 'void_gift_voucher', 'reverse_gift_voucher_redemption'], true)) {
            $this->voucher($actor, $targetId);

            return 'gift_voucher';
        }

        return null;
    }

    /**
     * Resolve an owned active retail store.
     */
    private function store(User $actor, int $storeId): Store
    {
        $query = Store::query();
        Store::scopeForUser($query, $actor->resolveScopeUser());
        $store = Typer::assertInstance($query->whereKey($storeId)->firstOrFail(), Store::class);

        if ($store->isWarehouse()) {
            \abort(404);
        }

        return $store;
    }

    /**
     * Resolve an owned worker.
     */
    private function worker(User $actor, int $workerId): Worker
    {
        $query = Worker::query();
        Worker::scopeForUser($query, $actor->resolveScopeUser());

        return Typer::assertInstance($query->whereKey($workerId)->firstOrFail(), Worker::class);
    }

    /**
     * Resolve an owned gift voucher without exposing its code.
     */
    private function voucher(User $actor, int $voucherId): GiftVoucher
    {
        $query = GiftVoucher::query()->with('giftVoucherBatch');
        GiftVoucher::scopeForUser($query, $actor->resolveScopeUser());

        return Typer::assertInstance($query->whereKey($voucherId)->firstOrFail(), GiftVoucher::class);
    }

    /**
     * Issue a voucher batch from the current human-configured branding.
     *
     * @param array<string, mixed> $values
     */
    private function issueVouchers(User $actor, array $values): int
    {
        $setting = GiftVoucherSetting::query()->where('user_id', $actor->getKey())->first();

        if (!$setting instanceof GiftVoucherSetting) {
            Thrower::default()->message('branding', \__('Configure gift voucher branding before issuing a batch.'))->throw();
        }

        return $this->vouchers->issue(
            $actor,
            $setting,
            Typer::parseInt($values['quantity'] ?? null),
            Typer::assertString($values['amount'] ?? null),
            Typer::parseNullableString($values['expires_on'] ?? null),
            $this->branding->snapshotLogo($setting),
        )->getKey();
    }

    /**
     * Create a recurring-expense version.
     *
     * @param array<string, mixed> $values
     */
    private function createRecurring(User $actor, Store $store, array $values): int
    {
        $effective = new CarbonImmutable(Typer::assertString($values['effective_period'] ?? null) . '-01');

        return $this->financial->createRecurringExpense($actor, $store, $effective->year, $effective->month, Typer::assertString($values['label'] ?? null), Typer::parseFloat($values['amount'] ?? null), Typer::parseInt($values['due_day'] ?? null), Typer::parseNullableString($values['note'] ?? null))->getKey();
    }

    /**
     * Update a recurring-expense version.
     *
     * @param array<string, mixed> $values
     */
    private function updateRecurring(User $actor, Store $store, int $targetId, array $values): int
    {
        $effective = new CarbonImmutable(Typer::assertString($values['effective_period'] ?? null) . '-01');
        $this->financial->changeRecurringExpense($actor, $store, $targetId, $effective->year, $effective->month, Typer::assertString($values['label'] ?? null), Typer::parseFloat($values['amount'] ?? null), Typer::parseInt($values['due_day'] ?? null), Typer::parseNullableString($values['note'] ?? null));

        return $targetId;
    }

    /**
     * Terminate a recurring expense.
     *
     * @param array<string, mixed> $values
     */
    private function terminateRecurring(User $actor, Store $store, int $targetId, array $values): int
    {
        $period = new CarbonImmutable(Typer::assertString($values['ends_before_period'] ?? null) . '-01');
        $this->financial->terminateRecurringExpense($actor, $store, $targetId, $period->year, $period->month);

        return $targetId;
    }

    /**
     * Decode a bounded JSON object from the locked mutation envelope.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function json(array $arguments, string $key): array
    {
        $json = Typer::parseNullableString($arguments[$key] ?? null) ?? '{}';

        return Typer::assertStringKeyArray(Typer::assertArray(\json_decode($json, true, 32, \JSON_THROW_ON_ERROR)));
    }

    /**
     * Describe the normal records/effects shown before approval.
     *
     * @return list<string>
     */
    private function effects(string $identifier): array
    {
        return match (true) {
            \str_contains($identifier, 'payroll') => ['Updates the selected payroll month through PayrollReportService.', 'Preserves report lifecycle, snapshot, and activity behavior.'],
            \str_contains($identifier, 'gift_voucher'), \str_contains($identifier, 'voucher_branding') => ['Runs the normal gift-voucher lifecycle service.', 'Creates the same voucher events and operational activity as the human action.'],
            default => ['Updates the selected income/expense report through FinancialReportService.', 'Preserves report lifecycle, versions, and activity behavior.'],
        };
    }

    /**
     * Return a safe result type without voucher codes or sensitive fields.
     */
    private function resultType(string $identifier): string
    {
        return match (true) {
            \str_contains($identifier, 'payroll') => 'payroll',
            \str_contains($identifier, 'gift_voucher') => 'gift_voucher',
            \str_contains($identifier, 'voucher_branding') => 'gift_voucher_setting',
            default => 'financial_report',
        };
    }

    /**
     * Resolve a safe application link for the result.
     *
     * @param array<string, mixed> $context
     */
    private function url(string $identifier, Store|null $store, array $context): string
    {
        if (\str_contains($identifier, 'gift_voucher') || \str_contains($identifier, 'voucher_branding')) {
            return Resolver::resolveUrlGenerator()->route('gift-vouchers.index');
        }

        return Resolver::resolveUrlGenerator()->route(
            \str_contains($identifier, 'payroll') ? 'payroll.index' : 'income-expenses.index',
            [
                'store_id' => $store?->getKey(),
                'year' => Typer::parseNullableInt($context['year'] ?? null),
                'month' => Typer::parseNullableInt($context['month'] ?? null),
            ],
        );
    }
}
