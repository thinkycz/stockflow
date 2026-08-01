<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    CalendarClock,
    Copy,
    LockKeyhole,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
    UnlockKeyhole,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FieldError from '@/components/ui/FieldError.vue';
import FilterField from '@/components/ui/FilterField.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';

type FinancialRow = {
    id: string;
    manual_row_id?: number;
    kind: 'automatic' | 'manual';
    direction: 'income' | 'expense';
    source_type:
        'revenue' | 'stock_movement' | 'wage' | 'recurring_expense' | null;
    source_key: string | null;
    label: string;
    occurred_on: string | null;
    calculated_amount: number;
    override_amount: number | null;
    effective_amount: number;
    note: string | null;
    details: Record<string, number | string>;
};

type FinancialReport = {
    income_rows: FinancialRow[];
    expense_rows: FinancialRow[];
    totals: { income: number; expenses: number; profit: number };
    report: {
        id: number | null;
        status: 'open' | 'closed';
        closed_at: string | null;
        reopened_at: string | null;
    };
};

type RecurringExpense = {
    id: number;
    label: string;
    amount: number;
    due_day: number;
    note: string | null;
    starts_on: string;
    ends_before: string | null;
    effective_from: string;
    status: 'active' | 'upcoming' | 'ended';
};

const props = defineProps<{
    active_store: { id: number; name: string; is_warehouse: boolean } | null;
    filters: { year: number; month: number };
    financial_report: FinancialReport | null;
    recurring_expenses: RecurringExpense[];
}>();

const { t, locale } = useI18n();
useBoundLocale();
const route = useRoute();
const dialog = useDialog();
const manualModalOpen = ref(false);
const editingManualRow = ref<FinancialRow | null>(null);
const overrideModalOpen = ref(false);
const overridingRow = ref<FinancialRow | null>(null);
const lifecycleProcessing = ref(false);
const recurringManagerOpen = ref(false);
const recurringFormOpen = ref(false);
const editingRecurringExpense = ref<RecurringExpense | null>(null);
const terminationModalOpen = ref(false);
const terminatingRecurringExpense = ref<RecurringExpense | null>(null);

const manualForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    direction: 'expense' as 'income' | 'expense',
    label: '',
    occurred_on: `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}-01`,
    amount: '',
    note: '',
});

const overrideForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    source_type: 'revenue',
    source_key: '',
    amount: '',
});

const recurringForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    effective_period: selectedPeriod(),
    label: '',
    amount: '',
    due_day: '1',
    note: '',
});

const terminationForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    ends_before_period: selectedPeriod(),
});

function selectedPeriod(): string {
    return `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}`;
}

function followingPeriod(value: string): string {
    const [year, month] = value.split('-').map(Number);
    const next = new Date(Date.UTC(year, month, 1));
    return `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}`;
}

function openRecurringForm(expense: RecurringExpense | null = null): void {
    editingRecurringExpense.value = expense;
    recurringForm.clearErrors();
    recurringForm.year = props.filters.year;
    recurringForm.month = props.filters.month;
    recurringForm.label = expense?.label ?? '';
    recurringForm.amount = expense ? String(expense.amount) : '';
    recurringForm.due_day = expense ? String(expense.due_day) : '1';
    recurringForm.note = expense?.note ?? '';
    recurringForm.effective_period =
        expense && expense.starts_on > selectedPeriod()
            ? expense.starts_on
            : selectedPeriod();
    recurringManagerOpen.value = false;
    recurringFormOpen.value = true;
}

function submitRecurringExpense(): void {
    const options = { onSuccess: () => (recurringFormOpen.value = false) };
    if (editingRecurringExpense.value) {
        recurringForm.put(
            route(
                'income-expenses.recurring-expenses.update',
                editingRecurringExpense.value.id,
            ),
            options,
        );
        return;
    }
    recurringForm.post(
        route('income-expenses.recurring-expenses.store'),
        options,
    );
}

function openTermination(expense: RecurringExpense): void {
    terminatingRecurringExpense.value = expense;
    terminationForm.clearErrors();
    terminationForm.year = props.filters.year;
    terminationForm.month = props.filters.month;
    terminationForm.ends_before_period = followingPeriod(
        expense.starts_on > selectedPeriod()
            ? expense.starts_on
            : selectedPeriod(),
    );
    recurringManagerOpen.value = false;
    terminationModalOpen.value = true;
}

function submitTermination(): void {
    if (!terminatingRecurringExpense.value) return;
    terminationForm.post(
        route(
            'income-expenses.recurring-expenses.terminate',
            terminatingRecurringExpense.value.id,
        ),
        { onSuccess: () => (terminationModalOpen.value = false) },
    );
}

function money(value: number): string {
    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 2,
    }).format(value);
}

function date(value: string | null): string {
    return value === null
        ? '—'
        : new Intl.DateTimeFormat(locale.value).format(
              new Date(`${value}T12:00:00`),
          );
}

function changeMonth(value: string): void {
    const [year, month] = value.split('-').map(Number);
    if (year && month) {
        router.get(
            route('income-expenses.index'),
            { year, month },
            { preserveState: true },
        );
    }
}

function openManual(
    row: FinancialRow | null = null,
    direction: 'income' | 'expense' = 'expense',
): void {
    editingManualRow.value = row;
    manualForm.clearErrors();
    manualForm.year = props.filters.year;
    manualForm.month = props.filters.month;
    manualForm.direction = row?.direction ?? direction;
    manualForm.label = row?.label ?? '';
    manualForm.occurred_on =
        row?.occurred_on ??
        `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}-01`;
    manualForm.amount = row ? String(row.effective_amount) : '';
    manualForm.note = row?.note ?? '';
    manualModalOpen.value = true;
}

function submitManual(): void {
    const options = { onSuccess: () => (manualModalOpen.value = false) };
    if (editingManualRow.value?.manual_row_id) {
        manualForm.put(
            route(
                'income-expenses.manual-rows.update',
                editingManualRow.value.manual_row_id,
            ),
            options,
        );
        return;
    }
    manualForm.post(route('income-expenses.manual-rows.store'), options);
}

async function deleteManual(row: FinancialRow): Promise<void> {
    if (
        !row.manual_row_id ||
        !(await dialog.confirm({
            title: `${t('common.delete')}: ${row.label}`,
            message: t('income_expenses.confirm_delete'),
            confirmLabel: t('common.delete'),
            variant: 'danger',
        }))
    ) {
        return;
    }
    router.delete(
        route('income-expenses.manual-rows.destroy', row.manual_row_id),
        { data: { year: props.filters.year, month: props.filters.month } },
    );
}

function openOverride(row: FinancialRow): void {
    if (row.source_type === null || row.source_key === null) return;
    overridingRow.value = row;
    overrideForm.clearErrors();
    overrideForm.year = props.filters.year;
    overrideForm.month = props.filters.month;
    overrideForm.source_type = row.source_type;
    overrideForm.source_key = row.source_key;
    overrideForm.amount = String(row.effective_amount);
    overrideModalOpen.value = true;
}

function submitOverride(): void {
    overrideForm.post(route('income-expenses.overrides.store'), {
        onSuccess: () => (overrideModalOpen.value = false),
    });
}

function resetOverride(row: FinancialRow): void {
    if (row.source_type === null || row.source_key === null) return;
    router.delete(route('income-expenses.overrides.destroy'), {
        data: {
            year: props.filters.year,
            month: props.filters.month,
            source_type: row.source_type,
            source_key: row.source_key,
        },
    });
}

async function lifecycle(
    action: 'copy-previous' | 'close' | 'reopen',
): Promise<void> {
    const confirmation =
        action === 'close'
            ? t('income_expenses.confirm_close')
            : action === 'reopen'
              ? t('income_expenses.confirm_reopen')
              : null;
    if (
        confirmation !== null &&
        !(await dialog.confirm({
            title:
                action === 'close'
                    ? t('income_expenses.close')
                    : t('income_expenses.reopen'),
            message: confirmation,
            confirmLabel:
                action === 'close'
                    ? t('income_expenses.close')
                    : t('income_expenses.reopen'),
            variant: action === 'close' ? 'warning' : 'default',
        }))
    )
        return;
    lifecycleProcessing.value = true;
    router.post(
        route(`income-expenses.${action}`),
        { year: props.filters.year, month: props.filters.month },
        { onFinish: () => (lifecycleProcessing.value = false) },
    );
}

function rowSecondary(row: FinancialRow): string | null {
    if (row.source_type === 'revenue') {
        return t('income_expenses.commission_detail', {
            gross: money(Number(row.details.gross_amount ?? 0)),
            rate: Number(row.details.commission_rate ?? 0) * 100,
            commission: money(Number(row.details.commission_amount ?? 0)),
        });
    }
    if (row.source_type === 'wage') {
        return t('income_expenses.payroll_wage_detail', {
            base: money(Number(row.details.base_amount ?? 0)),
            tips: money(Number(row.details.tip_amount ?? 0)),
            deductions: money(Number(row.details.deduction_amount ?? 0)),
        });
    }
    return row.note;
}

function rowHref(row: FinancialRow): string | null {
    if (row.source_type === 'revenue') {
        return route('statements.index', {
            store_id: props.active_store?.id ?? null,
            year: props.filters.year,
            month: props.filters.month,
        });
    }
    if (row.source_type === 'stock_movement' && row.details.movement_id) {
        return route('stock-movements.show', Number(row.details.movement_id));
    }
    if (row.source_type === 'wage' && row.details.worker_id) {
        return route('payroll.print', {
            store_id: props.active_store?.id ?? null,
            year: props.filters.year,
            month: props.filters.month,
            worker_id: Number(row.details.worker_id),
        });
    }
    return null;
}
</script>

<template>
    <AppLayout :title="t('income_expenses.title')">
        <Head :title="t('income_expenses.title')" />

        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <div class="flex items-center gap-3">
                        <h1
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ t('income_expenses.title') }}
                        </h1>
                        <Badge
                            v-if="financial_report"
                            :variant="
                                financial_report.report.status === 'closed'
                                    ? 'success'
                                    : 'warning'
                            "
                        >
                            {{
                                t(
                                    `income_expenses.status.${financial_report.report.status}`,
                                )
                            }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{
                            t('income_expenses.subtitle', {
                                store: active_store?.name ?? '—',
                            })
                        }}
                    </p>
                    <StoreContextIndicator />
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <FilterField
                        for="income_expenses_month"
                        :label="t('income_expenses.month')"
                    >
                        <MonthPicker
                            id="income_expenses_month"
                            :model-value="`${filters.year}-${String(filters.month).padStart(2, '0')}`"
                            @change="changeMonth"
                        />
                    </FilterField>
                    <Button
                        v-if="financial_report"
                        variant="secondary"
                        @click="recurringManagerOpen = true"
                    >
                        <CalendarClock :size="15" />{{
                            t('income_expenses.recurring.manage')
                        }}
                    </Button>
                    <template v-if="financial_report?.report.status === 'open'">
                        <Button
                            variant="secondary"
                            :disabled="lifecycleProcessing"
                            @click="lifecycle('copy-previous')"
                        >
                            <Copy :size="15" />{{
                                t('income_expenses.copy_previous')
                            }}
                        </Button>
                        <Button
                            variant="warning"
                            :disabled="lifecycleProcessing"
                            @click="lifecycle('close')"
                        >
                            <LockKeyhole :size="15" />{{
                                t('income_expenses.close')
                            }}
                        </Button>
                    </template>
                    <Button
                        v-if="financial_report?.report.status === 'closed'"
                        variant="secondary"
                        :disabled="lifecycleProcessing"
                        @click="lifecycle('reopen')"
                    >
                        <UnlockKeyhole :size="15" />{{
                            t('income_expenses.reopen')
                        }}
                    </Button>
                </div>
            </header>

            <EmptyState
                v-if="!financial_report"
                :title="t('income_expenses.warehouse_title')"
                :description="t('income_expenses.warehouse_description')"
            />

            <template v-else>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Card padded>
                        <div class="flex items-center gap-3 text-emerald-700">
                            <ArrowUpCircle :size="20" />
                            <span
                                class="text-xs font-semibold uppercase tracking-wider"
                                >{{ t('income_expenses.total_income') }}</span
                            >
                        </div>
                        <p class="mt-3 font-heading text-2xl font-bold">
                            {{ money(financial_report.totals.income) }}
                        </p>
                    </Card>
                    <Card padded>
                        <div class="flex items-center gap-3 text-rose-700">
                            <ArrowDownCircle :size="20" />
                            <span
                                class="text-xs font-semibold uppercase tracking-wider"
                                >{{ t('income_expenses.total_expenses') }}</span
                            >
                        </div>
                        <p class="mt-3 font-heading text-2xl font-bold">
                            {{ money(financial_report.totals.expenses) }}
                        </p>
                    </Card>
                    <Card padded>
                        <div
                            class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                        >
                            {{ t('income_expenses.profit') }}
                        </div>
                        <p
                            :class="[
                                'mt-3 font-heading text-2xl font-bold',
                                financial_report.totals.profit >= 0
                                    ? 'text-emerald-700'
                                    : 'text-rose-700',
                            ]"
                        >
                            {{ money(financial_report.totals.profit) }}
                        </p>
                    </Card>
                </div>

                <section
                    v-for="section in [
                        { key: 'income', rows: financial_report.income_rows },
                        { key: 'expense', rows: financial_report.expense_rows },
                    ]"
                    :key="section.key"
                    class="space-y-4"
                >
                    <div class="flex items-center justify-between px-1">
                        <h2 class="font-heading text-lg font-bold">
                            {{ t(`income_expenses.sections.${section.key}`) }}
                        </h2>
                        <Button
                            v-if="financial_report.report.status === 'open'"
                            variant="secondary"
                            size="compact"
                            @click="
                                openManual(
                                    null,
                                    section.key === 'income'
                                        ? 'income'
                                        : 'expense',
                                )
                            "
                        >
                            <Plus :size="14" />
                            {{
                                t(
                                    section.key === 'income'
                                        ? 'income_expenses.add_income'
                                        : 'income_expenses.add_expense',
                                )
                            }}
                        </Button>
                    </div>
                    <DataTable table-class="md:min-w-[760px]">
                        <thead
                            class="bg-surface-container-low text-[11px] uppercase tracking-wider text-on-surface-variant"
                        >
                            <tr>
                                <th class="px-5 py-3">
                                    {{ t('income_expenses.date') }}
                                </th>
                                <th class="px-5 py-3">
                                    {{ t('income_expenses.item') }}
                                </th>
                                <th class="px-5 py-3">
                                    {{ t('income_expenses.source') }}
                                </th>
                                <th class="px-5 py-3 text-right">
                                    {{ t('income_expenses.calculated') }}
                                </th>
                                <th class="px-5 py-3 text-right">
                                    {{ t('income_expenses.effective') }}
                                </th>
                                <th class="px-5 py-3 text-right">
                                    {{ t('income_expenses.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in section.rows"
                                :key="row.id"
                                :data-testid="`financial-row-${row.id.replace(':', '-')}`"
                                class="align-top"
                            >
                                <td
                                    class="whitespace-nowrap px-5 py-4 text-on-surface-variant"
                                >
                                    {{ date(row.occurred_on) }}
                                </td>
                                <td class="px-5 py-4">
                                    <Link
                                        v-if="rowHref(row)"
                                        :href="rowHref(row) ?? ''"
                                        :target="
                                            row.source_type === 'wage'
                                                ? '_blank'
                                                : undefined
                                        "
                                        class="font-semibold text-on-surface underline decoration-transparent underline-offset-4 transition-colors hover:text-primary hover:decoration-current"
                                    >
                                        {{ row.label }}
                                    </Link>
                                    <div v-else class="font-semibold">
                                        {{ row.label }}
                                    </div>
                                    <div
                                        v-if="rowSecondary(row)"
                                        class="mt-1 max-w-xl text-xs text-on-surface-variant"
                                    >
                                        {{ rowSecondary(row) }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge variant="neutral">{{
                                        t(
                                            `income_expenses.source_types.${row.kind === 'manual' ? 'manual' : row.source_type}`,
                                        )
                                    }}</Badge>
                                </td>
                                <td
                                    class="px-5 py-4 text-right text-on-surface-variant"
                                >
                                    {{ money(row.calculated_amount) }}
                                </td>
                                <td class="px-5 py-4 text-right font-semibold">
                                    <span
                                        :class="
                                            row.override_amount !== null
                                                ? 'text-amber-700'
                                                : ''
                                        "
                                        >{{ money(row.effective_amount) }}</span
                                    >
                                    <div
                                        v-if="row.override_amount !== null"
                                        class="text-[10px] uppercase tracking-wide text-amber-700"
                                    >
                                        {{ t('income_expenses.overridden') }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div
                                        v-if="
                                            financial_report.report.status ===
                                            'open'
                                        "
                                        class="flex justify-end gap-1"
                                    >
                                        <template
                                            v-if="row.kind === 'automatic'"
                                        >
                                            <Button
                                                variant="ghost"
                                                class="h-8 px-2"
                                                @click="openOverride(row)"
                                                ><Pencil :size="14" />{{
                                                    t(
                                                        'income_expenses.override',
                                                    )
                                                }}</Button
                                            >
                                            <Button
                                                v-if="
                                                    row.override_amount !== null
                                                "
                                                variant="ghost"
                                                class="h-8 px-2"
                                                @click="resetOverride(row)"
                                                ><RotateCcw :size="14" />{{
                                                    t('income_expenses.reset')
                                                }}</Button
                                            >
                                        </template>
                                        <template v-else>
                                            <Button
                                                variant="ghost"
                                                class="h-8 px-2"
                                                @click="openManual(row)"
                                                ><Pencil :size="14"
                                            /></Button>
                                            <Button
                                                variant="ghost"
                                                class="h-8 px-2 text-error-red"
                                                @click="deleteManual(row)"
                                                ><Trash2 :size="14"
                                            /></Button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="section.rows.length === 0">
                                <td
                                    colspan="6"
                                    data-label=""
                                    data-mobile-layout="stack"
                                    class="px-5 py-10 text-center text-on-surface-variant"
                                >
                                    {{ t('income_expenses.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </section>
            </template>
        </div>

        <Modal
            :open="recurringManagerOpen"
            :title="t('income_expenses.recurring.title')"
            class="max-w-3xl"
            @close="recurringManagerOpen = false"
        >
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm text-on-surface-variant">
                        {{ t('income_expenses.recurring.help') }}
                    </p>
                    <Button @click="openRecurringForm()">
                        <Plus :size="15" />{{
                            t('income_expenses.recurring.add')
                        }}
                    </Button>
                </div>
                <div
                    v-if="recurring_expenses.length === 0"
                    class="rounded-xl bg-surface-container-low px-4 py-8 text-center text-sm text-on-surface-variant"
                >
                    {{ t('income_expenses.recurring.empty') }}
                </div>
                <div v-else class="max-h-[55vh] space-y-3 overflow-y-auto pr-1">
                    <div
                        v-for="expense in recurring_expenses"
                        :key="expense.id"
                        :data-testid="`recurring-expense-${expense.id}`"
                        class="flex flex-col gap-3 rounded-xl border border-outline-glass p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{
                                    expense.label
                                }}</span>
                                <Badge
                                    :variant="
                                        expense.status === 'active'
                                            ? 'success'
                                            : expense.status === 'upcoming'
                                              ? 'warning'
                                              : 'neutral'
                                    "
                                    >{{
                                        t(
                                            `income_expenses.recurring.status.${expense.status}`,
                                        )
                                    }}</Badge
                                >
                            </div>
                            <p class="mt-1 text-sm text-on-surface-variant">
                                {{ money(expense.amount) }} ·
                                {{
                                    t(
                                        'income_expenses.recurring.due_day_value',
                                        {
                                            day: expense.due_day,
                                        },
                                    )
                                }}
                            </p>
                            <p class="mt-1 text-xs text-on-surface-variant">
                                {{
                                    t('income_expenses.recurring.validity', {
                                        from: expense.starts_on,
                                        until:
                                            expense.ends_before ??
                                            t(
                                                'income_expenses.recurring.indefinite',
                                            ),
                                    })
                                }}
                            </p>
                            <p
                                v-if="expense.note"
                                class="mt-1 text-xs text-on-surface-variant"
                            >
                                {{ expense.note }}
                            </p>
                        </div>
                        <div
                            v-if="expense.status !== 'ended'"
                            class="flex shrink-0 gap-1"
                        >
                            <Button
                                variant="ghost"
                                class="h-8 px-2"
                                @click="openRecurringForm(expense)"
                            >
                                <Pencil :size="14" />{{
                                    t('income_expenses.recurring.change')
                                }}
                            </Button>
                            <Button
                                variant="ghost"
                                class="h-8 px-2 text-error-red"
                                @click="openTermination(expense)"
                            >
                                {{ t('income_expenses.recurring.terminate') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal
            :open="recurringFormOpen"
            :title="
                editingRecurringExpense
                    ? t('income_expenses.recurring.change_title')
                    : t('income_expenses.recurring.add_title')
            "
            @close="recurringFormOpen = false"
        >
            <form class="space-y-4" @submit.prevent="submitRecurringExpense">
                <div class="space-y-2">
                    <Label for="recurring-label" required>{{
                        t('income_expenses.item')
                    }}</Label>
                    <Input
                        id="recurring-label"
                        v-model="recurringForm.label"
                        required
                    />
                    <FieldError :message="recurringForm.errors.label" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="recurring-amount" required>{{
                            t('income_expenses.amount')
                        }}</Label>
                        <Input
                            id="recurring-amount"
                            v-model="recurringForm.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                        />
                        <FieldError :message="recurringForm.errors.amount" />
                    </div>
                    <div class="space-y-2">
                        <Label for="recurring-day" required>{{
                            t('income_expenses.recurring.due_day')
                        }}</Label>
                        <Input
                            id="recurring-day"
                            v-model="recurringForm.due_day"
                            type="number"
                            min="1"
                            max="31"
                            required
                        />
                        <FieldError :message="recurringForm.errors.due_day" />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="recurring-effective" required>{{
                        editingRecurringExpense
                            ? t('income_expenses.recurring.effective_from')
                            : t('income_expenses.recurring.starts_on')
                    }}</Label>
                    <MonthPicker
                        id="recurring-effective"
                        v-model="recurringForm.effective_period"
                    />
                    <FieldError
                        :message="recurringForm.errors.effective_period"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="recurring-note">{{
                        t('income_expenses.note')
                    }}</Label>
                    <Textarea
                        id="recurring-note"
                        v-model="recurringForm.note"
                    />
                    <FieldError :message="recurringForm.errors.note" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        variant="secondary"
                        @click="recurringFormOpen = false"
                        >{{ t('common.cancel') }}</Button
                    >
                    <Button type="submit" :disabled="recurringForm.processing">
                        {{ t('common.save') }}
                    </Button>
                </div>
            </form>
        </Modal>

        <Modal
            :open="terminationModalOpen"
            :title="t('income_expenses.recurring.terminate_title')"
            @close="terminationModalOpen = false"
        >
            <form class="space-y-4" @submit.prevent="submitTermination">
                <p class="text-sm text-on-surface-variant">
                    {{
                        t('income_expenses.recurring.terminate_help', {
                            item: terminatingRecurringExpense?.label ?? '',
                        })
                    }}
                </p>
                <div class="space-y-2">
                    <Label for="recurring-ends-before" required>{{
                        t('income_expenses.recurring.ends_before')
                    }}</Label>
                    <MonthPicker
                        id="recurring-ends-before"
                        v-model="terminationForm.ends_before_period"
                    />
                    <FieldError
                        :message="terminationForm.errors.ends_before_period"
                    />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        variant="secondary"
                        @click="terminationModalOpen = false"
                        >{{ t('common.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        variant="warning"
                        :disabled="terminationForm.processing"
                    >
                        {{ t('income_expenses.recurring.confirm_terminate') }}
                    </Button>
                </div>
            </form>
        </Modal>

        <Modal
            :open="manualModalOpen"
            :title="
                editingManualRow
                    ? t('income_expenses.edit_row')
                    : t(
                          manualForm.direction === 'income'
                              ? 'income_expenses.add_income'
                              : 'income_expenses.add_expense',
                      )
            "
            @close="manualModalOpen = false"
        >
            <form class="space-y-4" @submit.prevent="submitManual">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="manual-direction" required>{{
                            t('income_expenses.direction')
                        }}</Label
                        ><Select
                            id="manual-direction"
                            v-model="manualForm.direction"
                            :options="[
                                {
                                    value: 'income',
                                    label: t('income_expenses.sections.income'),
                                },
                                {
                                    value: 'expense',
                                    label: t(
                                        'income_expenses.sections.expense',
                                    ),
                                },
                            ]"
                        /><FieldError :message="manualForm.errors.direction" />
                    </div>
                    <div class="space-y-2">
                        <Label for="manual-date" required>{{
                            t('income_expenses.date')
                        }}</Label
                        ><Input
                            id="manual-date"
                            v-model="manualForm.occurred_on"
                            type="date"
                            required
                        /><FieldError
                            :message="manualForm.errors.occurred_on"
                        />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="manual-label" required>{{
                        t('income_expenses.item')
                    }}</Label
                    ><Input
                        id="manual-label"
                        v-model="manualForm.label"
                        required
                    /><FieldError :message="manualForm.errors.label" />
                </div>
                <div class="space-y-2">
                    <Label for="manual-amount" required>{{
                        t('income_expenses.amount')
                    }}</Label
                    ><Input
                        id="manual-amount"
                        v-model="manualForm.amount"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    /><FieldError :message="manualForm.errors.amount" />
                </div>
                <div class="space-y-2">
                    <Label for="manual-note">{{
                        t('income_expenses.note')
                    }}</Label
                    ><Textarea
                        id="manual-note"
                        v-model="manualForm.note"
                    /><FieldError :message="manualForm.errors.note" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        variant="secondary"
                        @click="manualModalOpen = false"
                        >{{ t('common.cancel') }}</Button
                    ><Button type="submit" :disabled="manualForm.processing">{{
                        t('common.save')
                    }}</Button>
                </div>
            </form>
        </Modal>

        <Modal
            :open="overrideModalOpen"
            :title="
                t('income_expenses.override_title', {
                    item: overridingRow?.label ?? '',
                })
            "
            @close="overrideModalOpen = false"
        >
            <form class="space-y-4" @submit.prevent="submitOverride">
                <p class="text-sm text-on-surface-variant">
                    {{
                        t('income_expenses.override_help', {
                            calculated: money(
                                overridingRow?.calculated_amount ?? 0,
                            ),
                        })
                    }}
                </p>
                <div class="space-y-2">
                    <Label for="override-amount" required>{{
                        t('income_expenses.effective')
                    }}</Label
                    ><Input
                        id="override-amount"
                        v-model="overrideForm.amount"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    /><FieldError :message="overrideForm.errors.amount" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        variant="secondary"
                        @click="overrideModalOpen = false"
                        >{{ t('common.cancel') }}</Button
                    ><Button
                        type="submit"
                        :disabled="overrideForm.processing"
                        >{{ t('common.save') }}</Button
                    >
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
