<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock3, Save, UserRound } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FieldError from '@/components/ui/FieldError.vue';
import FilterField from '@/components/ui/FilterField.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { formatCzechDate } from '@/composables/useCzechDate';
import { formatMoney } from '@/lib/format';
import {
    useStatementEditor,
    type StatementEditorProps,
} from '@/features/statements/useStatementEditor';

const props = defineProps<StatementEditorProps>();
const {
    t,
    route,
    todayFields,
    todayForm,
    editingRows,
    submitting,
    checkingAttendances,
    attendanceModalOpen,
    attendanceModalProcessing,
    attendanceWorkedSeconds,
    attendanceDuration,
    monthValue,
    selectMonth,
    rowTotal,
    rowCashTotal,
    showTodayPanel,
    todayTotal,
    totals,
    updateEditing,
    editingKey,
    closeAttendanceModal,
    save,
    saveToday,
    submitPendingSave,
} = useStatementEditor(props);
</script>

<template>
    <AppLayout :title="t('statements.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('statements.title')"
                :subtitle="t('statements.subtitle')"
            >
                <template #context>
                    <StoreContextIndicator :store="props.store" />
                </template>
                <template #actions>
                    <div
                        class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end"
                    >
                        <FilterField
                            for="statement_month"
                            :label="t('statements.month')"
                        >
                            <MonthPicker
                                id="statement_month"
                                :model-value="monthValue"
                                @change="selectMonth"
                            />
                        </FilterField>
                        <Link
                            v-if="props.statement"
                            :href="
                                route('statements.history', {
                                    statement: props.statement.id,
                                })
                            "
                        >
                            <Button variant="secondary">
                                {{ t('statements.actions.history') }} →
                            </Button>
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <Card v-if="props.bank_reconciliation" padded>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <h2
                                class="font-heading text-base font-bold text-on-surface"
                            >
                                {{ t('statements.bank_control.title') }}
                            </h2>
                            <Badge
                                :variant="
                                    props.bank_reconciliation.status ===
                                    'confirmed'
                                        ? 'success'
                                        : props.bank_reconciliation.status ===
                                            'failed'
                                          ? 'danger'
                                          : props.bank_reconciliation.status ===
                                              'review'
                                            ? 'warning'
                                            : 'neutral'
                                "
                            >
                                {{
                                    t(
                                        `bank_statements.status.${props.bank_reconciliation.status}`,
                                    )
                                }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            <template
                                v-if="
                                    props.bank_reconciliation.status ===
                                    'confirmed'
                                "
                            >
                                {{
                                    t(
                                        'statements.bank_control.summary',
                                        props.bank_reconciliation.counts,
                                    )
                                }}
                            </template>
                            <template v-else>{{
                                t('statements.bank_control.description')
                            }}</template>
                        </p>
                    </div>
                    <Link
                        :href="
                            props.bank_reconciliation.statement_id
                                ? route('bank-statements.show', {
                                      bankStatement:
                                          props.bank_reconciliation
                                              .statement_id,
                                  })
                                : route('bank-statements.index')
                        "
                    >
                        <Button variant="secondary" size="compact">
                            {{ t('statements.bank_control.action') }} →
                        </Button>
                    </Link>
                </div>
            </Card>

            <Card v-if="showTodayPanel && props.today_day" padded>
                <form class="space-y-5" @submit.prevent="saveToday">
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <h2
                                class="font-heading text-lg font-bold text-on-surface"
                            >
                                {{ t('statements.quick_entry.title') }}
                            </h2>
                            <p class="mt-1 text-sm text-on-surface-variant">
                                {{
                                    t('statements.quick_entry.description', {
                                        date: formatCzechDate(
                                            props.today_day.date,
                                        ),
                                    })
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low px-4 py-2"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('statements.quick_entry.total') }}
                            </p>
                            <p
                                class="font-heading text-lg font-bold text-on-surface"
                            >
                                {{ formatMoney(todayTotal) }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                    >
                        <div
                            v-for="field in todayFields"
                            :key="field"
                            class="space-y-2"
                        >
                            <Label :for="`today_${field}`">
                                {{ t(`statements.columns.${field}`) }}
                            </Label>
                            <Input
                                :id="`today_${field}`"
                                v-model="todayForm[field]"
                                type="number"
                                step="0.01"
                                min="0"
                                class="text-right"
                            />
                            <FieldError :message="todayForm.errors[field]" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="
                                todayForm.processing || checkingAttendances
                            "
                        >
                            <Save :size="14" />
                            {{ t('statements.quick_entry.save') }}
                        </Button>
                    </div>
                </form>
            </Card>

            <EmptyState
                v-if="!props.statement"
                :title="t('statements.empty.title')"
                :description="t('statements.empty.description')"
            />

            <template v-else>
                <section class="space-y-4">
                    <DataTable density="compact">
                        <thead>
                            <tr>
                                <th class="min-w-[6rem]">
                                    {{ t('statements.columns.day') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.cash') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.card') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.wolt') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.bolt') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.bolt_cash') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.foodora') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.total') }}
                                </th>
                                <th
                                    v-if="props.is_admin"
                                    class="min-w-[5rem] text-center"
                                >
                                    {{ t('statements.columns.cash_checked') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="day in editingRows"
                                :key="editingKey(day)"
                            >
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ formatCzechDate(day.date) }}
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.cash || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        :disabled="!props.editable"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'cash',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.card || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        :disabled="!props.editable"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'card',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.wolt || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        :disabled="!props.editable"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'wolt',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.bolt || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        :disabled="!props.editable"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'bolt',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="
                                            String(day.bolt_cash || 0)
                                        "
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        :disabled="!props.editable"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'bolt_cash',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="String(day.foodora || 0)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="text-right"
                                        :disabled="!props.editable"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'foodora',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    <div>
                                        {{ formatMoney(rowTotal(day)) }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-[0.65rem] font-normal text-on-surface-variant"
                                    >
                                        {{
                                            t(
                                                'statements.columns.cash_of_total',
                                                {
                                                    amount: formatMoney(
                                                        rowCashTotal(day),
                                                    ),
                                                },
                                            )
                                        }}
                                    </div>
                                </td>
                                <td v-if="props.is_admin" class="text-center">
                                    <Checkbox
                                        :model-value="day.cash_checked"
                                        :disabled="!props.editable"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    editingKey(day),
                                                    'cash_checked',
                                                    value,
                                                )
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th
                                    class="text-left text-xs font-semibold text-on-surface-variant"
                                >
                                    Σ
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.cash) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.card) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.wolt) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.bolt) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.bolt_cash) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals.foodora) }}
                                </th>
                                <th
                                    class="text-right text-xs font-semibold text-on-surface"
                                >
                                    <div>
                                        {{ formatMoney(totals.total) }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-[0.65rem] font-normal text-on-surface-variant"
                                    >
                                        {{
                                            t(
                                                'statements.columns.cash_of_total',
                                                {
                                                    amount: formatMoney(
                                                        totals.cash +
                                                            totals.bolt_cash,
                                                    ),
                                                },
                                            )
                                        }}
                                    </div>
                                </th>
                                <th v-if="props.is_admin">
                                    {{ t('statements.columns.cash_checked') }}
                                </th>
                            </tr>
                        </tfoot>
                    </DataTable>

                    <div
                        v-if="props.editable"
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                    >
                        <Button
                            type="button"
                            :disabled="submitting || checkingAttendances"
                            @click="save"
                        >
                            <Save :size="14" />
                            {{ t('statements.actions.save') }}
                        </Button>
                    </div>
                </section>
            </template>
        </div>

        <Modal
            :open="attendanceModalOpen"
            :title="t('statements.attendance_close.title')"
            @close="closeAttendanceModal"
        >
            <p class="text-sm leading-6 text-on-surface-variant">
                {{ t('statements.attendance_close.description') }}
            </p>
            <div
                class="mt-4 overflow-hidden rounded-xl border border-outline-glass"
            >
                <div
                    class="flex items-center justify-between bg-surface-container-low px-4 py-3"
                >
                    <span
                        id="active-attendance-workers-label"
                        class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant"
                    >
                        {{ t('statements.attendance_close.workers') }}
                    </span>
                    <Badge variant="success">
                        {{ props.active_attendances.length }}
                    </Badge>
                </div>
                <ul
                    aria-labelledby="active-attendance-workers-label"
                    class="max-h-64 divide-y divide-outline-glass overflow-y-auto bg-surface-container-lowest"
                >
                    <li
                        v-for="attendance in props.active_attendances"
                        :key="attendance.worker_id"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <UserRound :size="18" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <strong
                                class="block truncate text-sm font-semibold text-on-surface"
                            >
                                {{ attendance.worker_name }}
                            </strong>
                            <span
                                class="mt-0.5 flex items-center gap-1.5 text-xs font-medium"
                                :class="
                                    attendance.is_on_break
                                        ? 'text-amber-700'
                                        : 'text-emerald-700'
                                "
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="
                                        attendance.is_on_break
                                            ? 'bg-amber-400'
                                            : 'bg-emerald-500'
                                    "
                                    aria-hidden="true"
                                ></span>
                                {{
                                    t(
                                        attendance.is_on_break
                                            ? 'statements.attendance_close.on_break'
                                            : 'statements.attendance_close.active_status',
                                    )
                                }}
                            </span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span
                                class="block text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant"
                            >
                                {{
                                    t('statements.attendance_close.worked_time')
                                }}
                            </span>
                            <span
                                class="mt-1 flex items-center justify-end gap-1.5 font-mono text-sm font-semibold tabular-nums text-on-surface"
                            >
                                <Clock3
                                    :size="14"
                                    class="text-on-surface-variant"
                                    aria-hidden="true"
                                />
                                {{
                                    attendanceDuration(
                                        attendanceWorkedSeconds(attendance),
                                    )
                                }}
                            </span>
                        </span>
                    </li>
                </ul>
            </div>

            <template #footer>
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="attendanceModalProcessing"
                    @click="submitPendingSave(false)"
                >
                    {{ t('statements.attendance_close.save_only') }}
                </Button>
                <Button
                    type="button"
                    :disabled="attendanceModalProcessing"
                    @click="submitPendingSave(true)"
                >
                    {{ t('statements.attendance_close.save_and_close') }}
                </Button>
            </template>
        </Modal>
    </AppLayout>
</template>
