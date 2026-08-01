<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
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
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type FinancialRow = {
    id: string;
    manual_row_id?: number;
    kind: 'automatic' | 'manual';
    direction: 'income' | 'expense';
    source_type: 'revenue' | 'stock_movement' | 'wage' | null;
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

const props = defineProps<{
    active_store: { id: number; name: string; is_warehouse: boolean } | null;
    filters: { year: number; month: number };
    financial_report: FinancialReport | null;
}>();

const { t, locale } = useI18n();
useBoundLocale();
const route = useRoute();
const manualModalOpen = ref(false);
const editingManualRow = ref<FinancialRow | null>(null);
const overrideModalOpen = ref(false);
const overridingRow = ref<FinancialRow | null>(null);
const lifecycleProcessing = ref(false);

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

function changeMonth(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    const [year, month] = value.split('-').map(Number);
    if (year && month) {
        router.get(
            route('income-expenses.index'),
            { year, month },
            { preserveState: true },
        );
    }
}

function openManual(row: FinancialRow | null = null): void {
    editingManualRow.value = row;
    manualForm.clearErrors();
    manualForm.year = props.filters.year;
    manualForm.month = props.filters.month;
    manualForm.direction = row?.direction ?? 'expense';
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

function deleteManual(row: FinancialRow): void {
    if (
        !row.manual_row_id ||
        !window.confirm(t('income_expenses.confirm_delete'))
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

function lifecycle(action: 'copy-previous' | 'close' | 'reopen'): void {
    const confirmation =
        action === 'close'
            ? t('income_expenses.confirm_close')
            : action === 'reopen'
              ? t('income_expenses.confirm_reopen')
              : null;
    if (confirmation !== null && !window.confirm(confirmation)) return;
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
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Input
                        type="month"
                        :model-value="`${filters.year}-${String(filters.month).padStart(2, '0')}`"
                        class="w-44"
                        :aria-label="t('income_expenses.month')"
                        @change="changeMonth"
                    />
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
                        <Button variant="secondary" @click="openManual()">
                            <Plus :size="15" />{{
                                t('income_expenses.add_row')
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
                        v-else-if="financial_report"
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
                        <span class="text-xs text-on-surface-variant">{{
                            t('income_expenses.rows_count', {
                                count: section.rows.length,
                            })
                        }}</span>
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
                                    <div class="font-semibold">
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
                                    <Link
                                        v-if="
                                            row.source_type ===
                                                'stock_movement' &&
                                            row.details.movement_id
                                        "
                                        :href="
                                            route(
                                                'stock-movements.show',
                                                row.details.movement_id,
                                            )
                                        "
                                        class="mt-1 block text-right text-xs font-semibold text-primary hover:underline"
                                        >{{
                                            t('income_expenses.open_document')
                                        }}</Link
                                    >
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
            :open="manualModalOpen"
            :title="
                editingManualRow
                    ? t('income_expenses.edit_row')
                    : t('income_expenses.add_row')
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
