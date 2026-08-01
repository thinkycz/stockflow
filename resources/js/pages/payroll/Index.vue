<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    LockKeyhole,
    Pencil,
    Plus,
    Printer,
    Trash2,
    UnlockKeyhole,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import type {
    PayrollAdjustment,
    PayrollReport,
    Payslip,
} from '@/types/payroll';

const props = defineProps<{
    active_store: { id: number; name: string; is_warehouse: boolean } | null;
    filters: { year: number; month: number };
    payroll_report: PayrollReport | null;
}>();

const { t, locale } = useI18n();
const route = useRoute();
const adjustmentModalOpen = ref(false);
const editingAdjustment = ref<PayrollAdjustment | null>(null);
const selectedPayslip = ref<Payslip | null>(null);
const lifecycleProcessing = ref(false);
const adjustmentForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    worker_id: 0,
    type: 'tip' as 'tip' | 'deduction',
    amount: '',
    reason: '',
});

function monthValue(): string {
    return (
        String(props.filters.year) +
        '-' +
        String(props.filters.month).padStart(2, '0')
    );
}

function duration(seconds: number | null): string {
    if (seconds === null) return '—';
    const minutes = Math.round(Math.abs(seconds) / 60);
    const sign = seconds < 0 ? '−' : '';
    return (
        sign +
        String(Math.floor(minutes / 60)) +
        ':' +
        String(minutes % 60).padStart(2, '0')
    );
}

function date(value: string): string {
    return new Intl.DateTimeFormat(locale.value).format(
        new Date(value + 'T12:00:00'),
    );
}

function time(value: string | null): string {
    if (value === null) return '—';
    return new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function changeMonth(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    const [year, month] = value.split('-').map(Number);
    if (year && month) {
        router.get(
            route('payroll.index'),
            { year, month },
            { preserveState: true },
        );
    }
}

function openAdjustment(
    payslip: Payslip,
    adjustment: PayrollAdjustment | null = null,
): void {
    selectedPayslip.value = payslip;
    editingAdjustment.value = adjustment;
    adjustmentForm.clearErrors();
    adjustmentForm.year = props.filters.year;
    adjustmentForm.month = props.filters.month;
    adjustmentForm.worker_id = payslip.worker_id;
    adjustmentForm.type = adjustment?.type ?? 'tip';
    adjustmentForm.amount = adjustment ? String(adjustment.amount) : '';
    adjustmentForm.reason = adjustment?.reason ?? '';
    adjustmentModalOpen.value = true;
}

function submitAdjustment(): void {
    const options = {
        onSuccess: () => {
            adjustmentModalOpen.value = false;
        },
    };
    if (editingAdjustment.value !== null) {
        adjustmentForm.put(
            route('payroll.adjustments.update', editingAdjustment.value.id),
            options,
        );
        return;
    }
    adjustmentForm.post(route('payroll.adjustments.store'), options);
}

function deleteAdjustment(adjustment: PayrollAdjustment): void {
    if (!window.confirm(t('payroll.confirm_delete_adjustment'))) return;
    router.delete(route('payroll.adjustments.destroy', adjustment.id), {
        data: { year: props.filters.year, month: props.filters.month },
    });
}

function lifecycle(action: 'close' | 'reopen'): void {
    if (!window.confirm(t('payroll.confirm_' + action))) return;
    lifecycleProcessing.value = true;
    router.post(
        route('payroll.' + action),
        { year: props.filters.year, month: props.filters.month },
        { onFinish: () => (lifecycleProcessing.value = false) },
    );
}
</script>

<template>
    <AppLayout :title="t('payroll.title')">
        <Head :title="t('payroll.title')" />

        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <div class="flex items-center gap-3">
                        <h1
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ t('payroll.title') }}
                        </h1>
                        <Badge
                            v-if="payroll_report"
                            :variant="
                                payroll_report.status === 'closed'
                                    ? 'success'
                                    : 'warning'
                            "
                        >
                            {{ t('payroll.status.' + payroll_report.status) }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{
                            t('payroll.subtitle', {
                                store: active_store?.name ?? '—',
                            })
                        }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Input
                        type="month"
                        :model-value="monthValue()"
                        class="w-44"
                        :aria-label="t('payroll.month')"
                        @change="changeMonth"
                    />
                    <Link
                        v-if="payroll_report"
                        :href="
                            route('payroll.print', {
                                year: filters.year,
                                month: filters.month,
                            })
                        "
                        target="_blank"
                    >
                        <Button variant="secondary">
                            <Printer :size="15" />{{ t('payroll.print_all') }}
                        </Button>
                    </Link>
                    <Button
                        v-if="payroll_report?.status === 'open'"
                        variant="warning"
                        :disabled="lifecycleProcessing"
                        @click="lifecycle('close')"
                    >
                        <LockKeyhole :size="15" />{{ t('payroll.close') }}
                    </Button>
                    <Button
                        v-else-if="payroll_report"
                        variant="secondary"
                        :disabled="lifecycleProcessing"
                        @click="lifecycle('reopen')"
                    >
                        <UnlockKeyhole :size="15" />{{ t('payroll.reopen') }}
                    </Button>
                </div>
            </header>

            <EmptyState
                v-if="!payroll_report"
                :title="t('payroll.warehouse_title')"
                :description="t('payroll.warehouse_description')"
            />

            <DataTable v-else table-class="md:min-w-[980px]">
                <thead
                    class="bg-surface-container-low text-[11px] uppercase tracking-wider text-on-surface-variant"
                >
                    <tr>
                        <th class="px-5 py-3">
                            {{ t('payroll.worker') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.planned') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.actual') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.base_amount') }}
                        </th>
                        <th class="px-5 py-3 text-right">
                            {{ t('payroll.adjustments') }}
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
                        :data-testid="'payroll-row-' + payslip.worker_id"
                        class="align-top"
                    >
                        <td data-mobile-layout="stack" class="px-5 py-4">
                            <details>
                                <summary
                                    class="cursor-pointer font-semibold text-on-surface"
                                >
                                    {{ payslip.worker_name }}
                                </summary>
                                <div class="mt-4 space-y-4">
                                    <div
                                        v-if="
                                            payslip.incomplete_count > 0 ||
                                            payslip.unmatched_count > 0
                                        "
                                        class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900"
                                    >
                                        {{
                                            t('payroll.attendance_warning', {
                                                incomplete:
                                                    payslip.incomplete_count,
                                                unmatched:
                                                    payslip.unmatched_count,
                                            })
                                        }}
                                    </div>
                                    <DataTable
                                        density="compact"
                                        variant="nested"
                                        table-class="text-xs md:min-w-[650px]"
                                    >
                                        <thead>
                                            <tr>
                                                <th>
                                                    {{ t('payroll.date') }}
                                                </th>
                                                <th>
                                                    {{ t('payroll.interval') }}
                                                </th>
                                                <th>
                                                    {{ t('payroll.planned') }}
                                                </th>
                                                <th>
                                                    {{ t('payroll.rate') }}
                                                </th>
                                                <th>
                                                    {{ t('payroll.amount') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="shift in payslip.shifts"
                                                :key="shift.id"
                                            >
                                                <td>
                                                    {{ date(shift.date) }}
                                                </td>
                                                <td>
                                                    {{ shift.start_time }}–{{
                                                        shift.end_time
                                                    }}
                                                </td>
                                                <td>
                                                    {{
                                                        duration(
                                                            shift.planned_minutes *
                                                                60,
                                                        )
                                                    }}
                                                </td>
                                                <td>
                                                    {{
                                                        formatMoney(
                                                            shift.hourly_rate,
                                                        )
                                                    }}
                                                </td>
                                                <td>
                                                    {{
                                                        formatMoney(
                                                            shift.amount,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </DataTable>
                                    <div
                                        v-if="payslip.attendance.length"
                                        class="text-xs text-on-surface-variant"
                                    >
                                        <p class="mb-2 font-semibold">
                                            {{
                                                t('payroll.attendance_records')
                                            }}
                                        </p>
                                        <div
                                            v-for="row in payslip.attendance"
                                            :key="row.id"
                                        >
                                            {{ date(row.date) }} ·
                                            {{ time(row.started_at) }}–{{
                                                time(row.ended_at)
                                            }}
                                            ·
                                            {{ duration(row.actual_seconds) }}
                                            <span
                                                v-if="row.shift_id === null"
                                                class="font-semibold text-amber-700"
                                            >
                                                {{ t('payroll.unmatched') }}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="payslip.adjustments.length"
                                        class="space-y-2"
                                    >
                                        <div
                                            v-for="adjustment in payslip.adjustments"
                                            :key="adjustment.id"
                                            class="flex items-center justify-between gap-3 rounded-lg bg-surface-container-low px-3 py-2 text-xs"
                                        >
                                            <span>
                                                {{
                                                    t(
                                                        'payroll.adjustment_types.' +
                                                            adjustment.type,
                                                    )
                                                }}
                                                ·
                                                {{ adjustment.reason }}
                                                ·
                                                {{
                                                    formatMoney(
                                                        adjustment.amount,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    payroll_report.status ===
                                                    'open'
                                                "
                                                class="flex gap-1"
                                            >
                                                <Button
                                                    variant="ghost"
                                                    class="h-7 px-2"
                                                    @click="
                                                        openAdjustment(
                                                            payslip,
                                                            adjustment,
                                                        )
                                                    "
                                                >
                                                    <Pencil :size="13" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    class="h-7 px-2 text-error-red"
                                                    @click="
                                                        deleteAdjustment(
                                                            adjustment,
                                                        )
                                                    "
                                                >
                                                    <Trash2 :size="13" />
                                                </Button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </td>
                        <td class="px-5 py-4 text-right">
                            {{ duration(payslip.planned_minutes * 60) }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            {{ duration(payslip.actual_seconds) }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            {{ formatMoney(payslip.base_amount) }}
                        </td>
                        <td class="px-5 py-4 text-right text-xs">
                            <div class="text-emerald-700">
                                +{{ formatMoney(payslip.tip_amount) }}
                            </div>
                            <div class="text-rose-700">
                                −{{ formatMoney(payslip.deduction_amount) }}
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right font-bold text-primary">
                            {{ formatMoney(payslip.final_amount) }}
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1">
                                <Button
                                    v-if="payroll_report.status === 'open'"
                                    variant="ghost"
                                    class="h-8 px-2"
                                    @click="openAdjustment(payslip, null)"
                                >
                                    <Plus :size="14" />{{
                                        t('payroll.add_adjustment')
                                    }}
                                </Button>
                                <Link
                                    :href="
                                        route('payroll.print', {
                                            year: filters.year,
                                            month: filters.month,
                                            worker_id: payslip.worker_id,
                                        })
                                    "
                                    target="_blank"
                                >
                                    <Button variant="ghost" class="h-8 px-2">
                                        <Printer :size="14" />
                                    </Button>
                                </Link>
                            </div>
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
            </DataTable>
        </div>

        <Modal
            :open="adjustmentModalOpen"
            :title="
                editingAdjustment
                    ? t('payroll.edit_adjustment')
                    : t('payroll.add_adjustment')
            "
            @close="adjustmentModalOpen = false"
        >
            <form class="space-y-4" @submit.prevent="submitAdjustment">
                <p class="text-sm font-semibold">
                    {{ selectedPayslip?.worker_name }}
                </p>
                <div class="space-y-2">
                    <Label for="adjustment-type" required>{{
                        t('payroll.adjustment_type')
                    }}</Label>
                    <Select
                        id="adjustment-type"
                        v-model="adjustmentForm.type"
                        :options="[
                            {
                                value: 'tip',
                                label: t('payroll.adjustment_types.tip'),
                            },
                            {
                                value: 'deduction',
                                label: t('payroll.adjustment_types.deduction'),
                            },
                        ]"
                    />
                    <FieldError :message="adjustmentForm.errors.type" />
                </div>
                <div class="space-y-2">
                    <Label for="adjustment-amount" required>{{
                        t('payroll.amount')
                    }}</Label>
                    <Input
                        id="adjustment-amount"
                        v-model="adjustmentForm.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    />
                    <FieldError :message="adjustmentForm.errors.amount" />
                </div>
                <div class="space-y-2">
                    <Label for="adjustment-reason" required>{{
                        t('payroll.reason')
                    }}</Label>
                    <Input
                        id="adjustment-reason"
                        v-model="adjustmentForm.reason"
                        required
                    />
                    <FieldError :message="adjustmentForm.errors.reason" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        variant="secondary"
                        @click="adjustmentModalOpen = false"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="adjustmentForm.processing">
                        {{ t('common.save') }}
                    </Button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>

<style scoped>
details table th,
details table td {
    border-bottom: 1px solid rgb(226 232 240 / 0.8);
    padding: 0.45rem;
    text-align: left;
}
</style>
