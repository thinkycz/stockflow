<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { HandCoins, LockKeyhole, Plus, UnlockKeyhole } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PayrollPrintMenu from '@/components/payroll/PayrollPrintMenu.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Combobox from '@/components/ui/Combobox.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FieldError from '@/components/ui/FieldError.vue';
import FilterField from '@/components/ui/FilterField.vue';
import Input from '@/components/ui/Input.vue';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import { withActionErrorToast } from '@/lib/action-errors';
import { formatMoney } from '@/lib/format';
import type { PayrollReport } from '@/types/payroll';

const props = defineProps<{
    active_store: {
        id: number;
        name: string;
        is_warehouse: boolean;
        is_active: boolean;
    } | null;
    filters: { year: number; month: number };
    payroll_report: PayrollReport | null;
    available_workers: { id: number; title: string }[];
}>();

const { t, locale } = useI18n();
const route = useRoute();
const dialog = useDialog();
const lifecycleProcessing = ref(false);
const workerModalOpen = ref(false);
const tipModalOpen = ref(false);
const workerForm = useForm({
    store_id: props.active_store?.id ?? null,
    year: props.filters.year,
    month: props.filters.month,
    worker_id: null as number | null,
});
const tipForm = useForm({
    store_id: props.active_store?.id ?? null,
    year: props.filters.year,
    month: props.filters.month,
    amount: '',
});
const tipEligiblePayslips = computed(
    () =>
        props.payroll_report?.payslips.filter(
            (payslip) => payslip.payable_hours > 0,
        ) ?? [],
);
const tipEligibleHours = computed(() =>
    tipEligiblePayslips.value.reduce(
        (total, payslip) => total + payslip.payable_hours,
        0,
    ),
);
const payrollTotals = computed(() =>
    (props.payroll_report?.payslips ?? []).reduce(
        (totals, payslip) => ({
            payable_hours: totals.payable_hours + payslip.payable_hours,
            base_amount: totals.base_amount + payslip.base_amount,
            tip_amount: totals.tip_amount + payslip.tip_amount,
            deduction_amount:
                totals.deduction_amount + payslip.deduction_amount,
            final_amount: totals.final_amount + payslip.final_amount,
        }),
        {
            payable_hours: 0,
            base_amount: 0,
            tip_amount: 0,
            deduction_amount: 0,
            final_amount: 0,
        },
    ),
);

function openWorkerModal(): void {
    workerForm.clearErrors();
    workerForm.year = props.filters.year;
    workerForm.month = props.filters.month;
    workerForm.worker_id = null;
    workerModalOpen.value = true;
}

function submitWorker(): void {
    workerForm.post(
        route('payroll.workers.store', {
            store_id: props.active_store?.id ?? null,
        }),
        {
            preserveScroll: true,
            onSuccess: () => (workerModalOpen.value = false),
        },
    );
}

function openTipModal(): void {
    tipForm.clearErrors();
    tipForm.year = props.filters.year;
    tipForm.month = props.filters.month;
    tipForm.amount = '';
    tipModalOpen.value = true;
}

function submitTips(): void {
    tipForm.post(
        route('payroll.tip-distributions.store', {
            store_id: props.active_store?.id ?? null,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                tipModalOpen.value = false;
                tipForm.reset('amount');
            },
        },
    );
}

function monthValue(): string {
    return `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}`;
}

function changeMonth(value: string): void {
    const [year, month] = value.split('-').map(Number);
    if (!year || !month) return;
    router.get(
        route('payroll.index'),
        { year, month, store_id: props.active_store?.id ?? null },
        { preserveState: true },
    );
}

function hours(value: number): string {
    return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(value)} h`;
}

async function lifecycle(action: 'close' | 'reopen'): Promise<void> {
    if (
        !(await dialog.confirm({
            title: t(`payroll.${action}`),
            message: t(`payroll.confirm_${action}`),
            confirmLabel: t(`payroll.${action}`),
            variant: action === 'close' ? 'warning' : 'default',
        }))
    )
        return;

    lifecycleProcessing.value = true;
    router.post(
        route(`payroll.${action}`),
        {
            store_id: props.active_store?.id ?? null,
            year: props.filters.year,
            month: props.filters.month,
        },
        withActionErrorToast({
            onFinish: () => (lifecycleProcessing.value = false),
        }),
    );
}
</script>

<template>
    <AppLayout :title="t('payroll.title')">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <PageHeader
                :title="t('payroll.title')"
                :subtitle="
                    t('payroll.subtitle', {
                        store: active_store?.name ?? '—',
                    })
                "
            >
                <template #context>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <StoreContextIndicator
                            :store="active_store"
                            class="mt-0"
                        />
                        <Badge
                            v-if="payroll_report"
                            :variant="
                                payroll_report.status === 'closed'
                                    ? 'success'
                                    : 'warning'
                            "
                        >
                            {{ t(`payroll.status.${payroll_report.status}`) }}
                        </Badge>
                    </div>
                </template>
                <template #actions>
                    <div class="flex flex-wrap items-end gap-2">
                        <FilterField
                            for="payroll_month"
                            :label="t('payroll.month')"
                        >
                            <MonthPicker
                                id="payroll_month"
                                :model-value="monthValue()"
                                @change="changeMonth"
                            />
                        </FilterField>
                        <PayrollPrintMenu
                            v-if="payroll_report"
                            :detailed-href="
                                route('payroll.print', {
                                    year: filters.year,
                                    month: filters.month,
                                    store_id: active_store?.id ?? null,
                                })
                            "
                            :simple-href="
                                route('payroll.print', {
                                    year: filters.year,
                                    month: filters.month,
                                    store_id: active_store?.id ?? null,
                                    simple: 1,
                                })
                            "
                        />
                        <Button
                            v-if="
                                active_store?.is_active &&
                                payroll_report?.status === 'open'
                            "
                            variant="secondary"
                            @click="openTipModal"
                        >
                            <HandCoins :size="15" />{{
                                t('payroll.distribute_tips')
                            }}
                        </Button>
                        <Button
                            v-if="
                                active_store?.is_active &&
                                payroll_report?.status === 'open' &&
                                available_workers.length > 0
                            "
                            variant="secondary"
                            @click="openWorkerModal"
                        >
                            <Plus :size="15" />{{ t('payroll.add_worker') }}
                        </Button>
                        <Button
                            v-if="
                                active_store?.is_active &&
                                payroll_report?.status === 'open'
                            "
                            variant="warning"
                            :disabled="lifecycleProcessing"
                            @click="lifecycle('close')"
                        >
                            <LockKeyhole :size="15" />{{ t('payroll.close') }}
                        </Button>
                        <Button
                            v-else-if="
                                active_store?.is_active && payroll_report
                            "
                            variant="secondary"
                            :disabled="lifecycleProcessing"
                            @click="lifecycle('reopen')"
                        >
                            <UnlockKeyhole :size="15" />{{
                                t('payroll.reopen')
                            }}
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <EmptyState
                v-if="!payroll_report"
                :title="t('payroll.warehouse_title')"
                :description="t('payroll.warehouse_description')"
            />

            <DataTable v-else table-class="md:min-w-[900px]">
                <thead
                    class="bg-surface-container-low text-[11px] tracking-wider text-on-surface-variant uppercase"
                >
                    <tr>
                        <th class="px-5 py-3">{{ t('payroll.worker') }}</th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.payable_hours') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.base_amount') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.adjustment_types.tip') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.adjustment_types.deduction') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.final_amount') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="payslip in payroll_report.payslips"
                        :key="payslip.worker_id"
                        :data-testid="`payroll-row-${payslip.worker_id}`"
                    >
                        <td data-mobile-layout="stack" class="px-5 py-4">
                            <div class="font-semibold text-on-surface">
                                {{ payslip.worker_name }}
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge
                                    v-if="payslip.wage_overridden"
                                    variant="warning"
                                >
                                    {{ t('payroll.wage_overridden') }}
                                </Badge>
                                <Badge
                                    v-if="
                                        payslip.incomplete_count > 0 ||
                                        payslip.unmatched_count > 0
                                    "
                                    variant="warning"
                                >
                                    {{
                                        t('payroll.attendance_needs_attention')
                                    }}
                                </Badge>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            {{ hours(payslip.payable_hours) }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            {{ formatMoney(payslip.base_amount) }}
                        </td>
                        <td class="px-5 py-4 text-right text-emerald-700">
                            {{ formatMoney(payslip.tip_amount) }}
                        </td>
                        <td class="px-5 py-4 text-right text-rose-700">
                            {{ formatMoney(payslip.deduction_amount) }}
                        </td>
                        <td class="px-5 py-4 text-right font-bold text-primary">
                            {{ formatMoney(payslip.final_amount) }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <Link
                                :href="
                                    route('payroll.show', {
                                        worker: payslip.worker_id,
                                        year: filters.year,
                                        month: filters.month,
                                        store_id: active_store?.id ?? null,
                                    })
                                "
                                class="inline-flex h-8 items-center justify-center rounded-xl border border-outline-glass bg-white px-2.5 text-xs font-semibold text-on-surface transition hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-2"
                            >
                                {{ t('common.detail') }}
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="payroll_report.payslips.length === 0">
                        <td
                            colspan="7"
                            data-label=""
                            data-mobile-layout="stack"
                            class="px-5 py-12 text-center text-on-surface-variant"
                        >
                            {{ t('payroll.empty') }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr data-testid="payroll-totals">
                        <th
                            data-label=""
                            class="text-left text-xs font-semibold text-on-surface-variant"
                        >
                            Σ
                        </th>
                        <td
                            class="text-right text-xs font-semibold text-on-surface-variant"
                        >
                            {{ hours(payrollTotals.payable_hours) }}
                        </td>
                        <td
                            class="text-right text-xs font-semibold text-on-surface-variant"
                        >
                            {{ formatMoney(payrollTotals.base_amount) }}
                        </td>
                        <td
                            class="text-right text-xs font-semibold text-emerald-700"
                        >
                            {{ formatMoney(payrollTotals.tip_amount) }}
                        </td>
                        <td
                            class="text-right text-xs font-semibold text-rose-700"
                        >
                            {{ formatMoney(payrollTotals.deduction_amount) }}
                        </td>
                        <td
                            class="text-right text-xs font-semibold text-on-surface"
                        >
                            {{ formatMoney(payrollTotals.final_amount) }}
                        </td>
                        <td data-label="" data-mobile-hidden></td>
                    </tr>
                </tfoot>
            </DataTable>
        </div>

        <Modal
            :open="tipModalOpen"
            :title="t('payroll.distribute_tips')"
            @close="tipModalOpen = false"
        >
            <form class="space-y-5" @submit.prevent="submitTips">
                <p class="text-sm leading-6 text-on-surface-variant">
                    {{ t('payroll.tip_distribution_help') }}
                </p>

                <div
                    class="flex flex-wrap gap-x-6 gap-y-2 rounded-xl bg-surface-container-low px-4 py-3 text-sm"
                >
                    <p>
                        <span class="text-on-surface-variant">
                            {{ t('payroll.tip_eligible_workers') }}:
                        </span>
                        <strong class="ml-1 text-on-surface">
                            {{ tipEligiblePayslips.length }}
                        </strong>
                    </p>
                    <p>
                        <span class="text-on-surface-variant">
                            {{ t('payroll.payable_hours') }}:
                        </span>
                        <strong class="ml-1 text-on-surface">
                            {{ hours(tipEligibleHours) }}
                        </strong>
                    </p>
                </div>

                <p
                    v-if="tipEligiblePayslips.length === 0"
                    class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-on-surface"
                >
                    {{ t('payroll.tip_distribution_no_hours') }}
                </p>

                <div class="space-y-2">
                    <Label for="payroll-tip-amount" required>
                        {{ t('payroll.tip_distribution_amount') }}
                    </Label>
                    <Input
                        id="payroll-tip-amount"
                        v-model="tipForm.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        autocomplete="off"
                        :invalid="Boolean(tipForm.errors.amount)"
                        autofocus
                        required
                    />
                    <FieldError :message="tipForm.errors.amount" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="secondary" @click="tipModalOpen = false">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            tipForm.processing ||
                            Number(tipForm.amount) <= 0 ||
                            tipEligiblePayslips.length === 0
                        "
                    >
                        <HandCoins :size="15" />{{
                            t('payroll.distribute_tips')
                        }}
                    </Button>
                </div>
            </form>
        </Modal>

        <Modal
            :open="workerModalOpen"
            :title="t('payroll.add_worker')"
            @close="workerModalOpen = false"
        >
            <form class="space-y-4" @submit.prevent="submitWorker">
                <div class="space-y-2">
                    <Label for="payroll-worker" required>
                        {{ t('payroll.worker') }}
                    </Label>
                    <Combobox
                        id="payroll-worker"
                        v-model="workerForm.worker_id"
                        :items="available_workers"
                        :placeholder="t('payroll.select_worker')"
                        :no-results-text="t('payroll.no_available_workers')"
                        :invalid="Boolean(workerForm.errors.worker_id)"
                        required
                    />
                    <FieldError :message="workerForm.errors.worker_id" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        variant="secondary"
                        @click="workerModalOpen = false"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            workerForm.processing ||
                            workerForm.worker_id === null
                        "
                    >
                        {{ t('payroll.add_worker') }}
                    </Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
