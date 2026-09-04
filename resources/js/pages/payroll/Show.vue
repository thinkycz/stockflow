<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Trash2 } from '@lucide/vue';
import PayrollPrintMenu from '@/features/payroll/components/PayrollPrintMenu.vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import DataTable from '@/components/ui/DataTable.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MetricCard from '@/components/ui/MetricCard.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import {
    usePayrollEditor,
    type PayrollEditorProps,
} from '@/features/payroll/usePayrollEditor';

const props = defineProps<PayrollEditorProps>();
const {
    t,
    route,
    adjustmentModalOpen,
    wageModalOpen,
    editingAdjustment,
    adjustmentForm,
    wageForm,
    formatMonth,
    date,
    time,
    duration,
    hours,
    openAdjustment,
    submitAdjustment,
    deleteAdjustment,
    openWageOverride,
    submitWageOverride,
    resetWageOverride,
    removeWorker,
} = usePayrollEditor(props);
</script>

<template>
    <AppLayout :title="payslip.worker_name">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header class="flex flex-col gap-4 lg:flex-row lg:justify-between">
                <div>
                    <Link
                        :href="
                            route('payroll.index', {
                                year: filters.year,
                                month: filters.month,
                                store_id: active_store.id,
                            })
                        "
                        class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
                    >
                        <ArrowLeft :size="16" />
                        {{ t('payroll.back_to_overview') }}
                    </Link>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ payslip.worker_name }}
                        </h1>
                        <Badge
                            :variant="
                                report.status === 'closed'
                                    ? 'success'
                                    : 'warning'
                            "
                        >
                            {{ t(`payroll.status.${report.status}`) }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ active_store.name }} · {{ formatMonth() }}
                    </p>
                </div>
                <div class="flex flex-wrap items-start gap-2">
                    <PayrollPrintMenu
                        :detailed-href="
                            route('payroll.print', {
                                year: filters.year,
                                month: filters.month,
                                store_id: active_store.id,
                                worker_id: payslip.worker_id,
                            })
                        "
                        :simple-href="
                            route('payroll.print', {
                                year: filters.year,
                                month: filters.month,
                                store_id: active_store.id,
                                worker_id: payslip.worker_id,
                                simple: 1,
                            })
                        "
                    />
                    <Button
                        v-if="
                            active_store.is_active && report.status === 'open'
                        "
                        variant="secondary"
                        @click="openWageOverride"
                    >
                        <Pencil :size="15" />{{ t('payroll.edit_wage') }}
                    </Button>
                    <Button
                        v-if="
                            active_store.is_active && report.status === 'open'
                        "
                        @click="openAdjustment()"
                    >
                        <Plus :size="15" />{{ t('payroll.add_adjustment') }}
                    </Button>
                    <Button
                        v-if="
                            active_store.is_active &&
                            report.status === 'open' &&
                            payslip.can_remove
                        "
                        variant="danger"
                        @click="removeWorker"
                    >
                        <Trash2 :size="15" />{{ t('payroll.remove_worker') }}
                    </Button>
                </div>
            </header>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <MetricCard
                    :title="t('payroll.payable_hours')"
                    :value="hours(payslip.payable_hours)"
                    :description="`${formatMoney(payslip.payable_hourly_rate)} / h`"
                />
                <MetricCard
                    :title="t('payroll.base_amount')"
                    :value="formatMoney(payslip.base_amount)"
                    :description="
                        payslip.wage_overridden
                            ? t('payroll.wage_overridden')
                            : undefined
                    "
                />
                <MetricCard
                    :title="t('payroll.adjustment_types.tip')"
                    :value="formatMoney(payslip.tip_amount)"
                />
                <MetricCard
                    :title="t('payroll.adjustment_types.deduction')"
                    :value="formatMoney(payslip.deduction_amount)"
                />
                <MetricCard
                    :title="t('payroll.final_amount')"
                    :value="formatMoney(payslip.final_amount)"
                />
            </div>

            <Alert
                v-if="
                    payslip.incomplete_count > 0 || payslip.unmatched_count > 0
                "
                variant="warning"
            >
                {{
                    t('payroll.attendance_warning', {
                        incomplete: payslip.incomplete_count,
                        unmatched: payslip.unmatched_count,
                    })
                }}
            </Alert>

            <section class="space-y-4">
                <div class="px-1">
                    <h2 class="font-heading text-lg font-bold text-on-surface">
                        {{ t('payroll.planned_shifts') }}
                    </h2>
                </div>
                <DataTable table-class="md:min-w-[760px]">
                    <thead>
                        <tr>
                            <th>{{ t('payroll.date') }}</th>
                            <th>{{ t('payroll.interval') }}</th>
                            <th class="text-right">
                                {{ t('payroll.planned') }}
                            </th>
                            <th class="text-right">
                                {{ t('payroll.actual') }}
                            </th>
                            <th class="text-right">{{ t('payroll.rate') }}</th>
                            <th class="text-right">
                                {{ t('payroll.amount') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="shift in payslip.shifts" :key="shift.id">
                            <td>{{ date(shift.date) }}</td>
                            <td>{{ shift.start_time }}–{{ shift.end_time }}</td>
                            <td class="text-right">
                                {{ duration(shift.planned_minutes * 60) }}
                            </td>
                            <td class="text-right">
                                {{ duration(shift.actual_seconds) }}
                            </td>
                            <td class="text-right">
                                {{ formatMoney(shift.hourly_rate) }}
                            </td>
                            <td class="text-right font-semibold">
                                {{ formatMoney(shift.amount) }}
                            </td>
                        </tr>
                        <tr v-if="payslip.shifts.length === 0">
                            <td colspan="6" class="text-center">
                                {{ t('payroll.no_shifts') }}
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </section>

            <section class="space-y-4">
                <div class="px-1">
                    <h2 class="font-heading text-lg font-bold text-on-surface">
                        {{ t('payroll.attendance_records') }}
                    </h2>
                </div>
                <DataTable table-class="md:min-w-[700px]">
                    <thead>
                        <tr>
                            <th>{{ t('payroll.date') }}</th>
                            <th>{{ t('payroll.interval') }}</th>
                            <th class="text-right">{{ t('payroll.break') }}</th>
                            <th class="text-right">
                                {{ t('payroll.actual') }}
                            </th>
                            <th>{{ t('payroll.attendance_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in payslip.attendance" :key="row.id">
                            <td>{{ date(row.date) }}</td>
                            <td>
                                {{ time(row.started_at) }}–{{
                                    time(row.ended_at)
                                }}
                            </td>
                            <td class="text-right">
                                {{ duration(row.break_seconds) }}
                            </td>
                            <td class="text-right">
                                {{ duration(row.actual_seconds) }}
                            </td>
                            <td>
                                <Badge
                                    v-if="row.shift_id === null"
                                    variant="warning"
                                >
                                    {{ t('payroll.unmatched') }}
                                </Badge>
                                <Badge
                                    v-else-if="row.actual_seconds === null"
                                    variant="warning"
                                >
                                    {{ t('payroll.incomplete') }}
                                </Badge>
                                <Badge v-else variant="success">
                                    {{ t('payroll.matched') }}
                                </Badge>
                            </td>
                        </tr>
                        <tr v-if="payslip.attendance.length === 0">
                            <td colspan="5" class="text-center">
                                {{ t('payroll.no_attendance') }}
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </section>

            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3 px-1">
                    <h2 class="font-heading text-lg font-bold text-on-surface">
                        {{ t('payroll.adjustments') }}
                    </h2>
                    <Button
                        v-if="
                            active_store.is_active && report.status === 'open'
                        "
                        variant="secondary"
                        size="compact"
                        @click="openAdjustment()"
                    >
                        <Plus :size="14" />{{ t('payroll.add_adjustment') }}
                    </Button>
                </div>
                <DataTable table-class="md:min-w-[640px]">
                    <thead>
                        <tr>
                            <th>{{ t('payroll.adjustment_type') }}</th>
                            <th>{{ t('payroll.reason') }}</th>
                            <th class="text-right">
                                {{ t('payroll.amount') }}
                            </th>
                            <th
                                v-if="
                                    active_store.is_active &&
                                    report.status === 'open'
                                "
                                class="text-right"
                            >
                                {{ t('common.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="adjustment in payslip.adjustments"
                            :key="adjustment.id"
                        >
                            <td>
                                {{
                                    t(
                                        `payroll.adjustment_types.${adjustment.type}`,
                                    )
                                }}
                            </td>
                            <td>{{ adjustment.reason }}</td>
                            <td class="text-right font-semibold">
                                {{ formatMoney(adjustment.amount) }}
                            </td>
                            <td
                                v-if="
                                    active_store.is_active &&
                                    report.status === 'open'
                                "
                                class="text-right"
                            >
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="compact"
                                        :title="t('common.edit')"
                                        @click="openAdjustment(adjustment)"
                                    >
                                        <Pencil :size="14" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="compact"
                                        class="text-error-red"
                                        :title="t('common.delete')"
                                        @click="deleteAdjustment(adjustment)"
                                    >
                                        <Trash2 :size="14" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="payslip.adjustments.length === 0">
                            <td
                                :colspan="report.status === 'open' ? 4 : 3"
                                class="text-center"
                            >
                                {{ t('payroll.no_adjustments') }}
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </section>
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
                <p class="text-sm font-semibold">{{ payslip.worker_name }}</p>
                <div class="space-y-2">
                    <Label for="adjustment-type" required>
                        {{ t('payroll.adjustment_type') }}
                    </Label>
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
                    <Label for="adjustment-amount" required>
                        {{ t('payroll.amount') }}
                    </Label>
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
                    <Label for="adjustment-reason" required>
                        {{ t('payroll.reason') }}
                    </Label>
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

        <Modal
            :open="wageModalOpen"
            :title="t('payroll.edit_wage')"
            @close="wageModalOpen = false"
        >
            <form class="space-y-4" @submit.prevent="submitWageOverride">
                <p class="text-sm font-semibold">{{ payslip.worker_name }}</p>
                <div class="space-y-2">
                    <Label for="wage-hours" required>
                        {{ t('payroll.payable_hours') }}
                    </Label>
                    <Input
                        id="wage-hours"
                        v-model="wageForm.hours"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    />
                    <FieldError :message="wageForm.errors.hours" />
                </div>
                <div class="space-y-2">
                    <Label for="wage-hourly-rate" required>
                        {{ t('payroll.hourly_rate') }}
                    </Label>
                    <Input
                        id="wage-hourly-rate"
                        v-model="wageForm.hourly_rate"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    />
                    <FieldError :message="wageForm.errors.hourly_rate" />
                </div>
                <p class="rounded-lg bg-surface-container-low p-3 text-sm">
                    {{ t('payroll.base_amount') }}:
                    <strong>
                        {{
                            formatMoney(
                                Number(wageForm.hours || 0) *
                                    Number(wageForm.hourly_rate || 0),
                            )
                        }}
                    </strong>
                </p>
                <div class="flex justify-between gap-2 pt-2">
                    <Button
                        v-if="payslip.wage_overridden"
                        variant="secondary"
                        @click="resetWageOverride"
                    >
                        {{ t('payroll.reset_wage') }}
                    </Button>
                    <span v-else />
                    <div class="flex gap-2">
                        <Button
                            variant="secondary"
                            @click="wageModalOpen = false"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="wageForm.processing">
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
