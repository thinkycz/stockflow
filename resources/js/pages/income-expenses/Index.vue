<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    CalendarClock,
    LockKeyhole,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
    UnlockKeyhole,
} from '@lucide/vue';
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
import PageHeader from '@/components/ui/PageHeader.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import Textarea from '@/components/ui/Textarea.vue';
import {
    useFinanceEntries,
    type FinanceEntriesProps,
} from '@/features/finance/useFinanceEntries';

const props = defineProps<FinanceEntriesProps>();
const {
    t,
    route,
    manualModalOpen,
    editingManualRow,
    overrideModalOpen,
    overridingRow,
    lifecycleProcessing,
    financialSections,
    manualForm,
    overrideForm,
    money,
    date,
    changeMonth,
    openManual,
    submitManual,
    deleteManual,
    openOverride,
    submitOverride,
    resetOverride,
    lifecycle,
    rowSecondary,
    rowHref,
} = useFinanceEntries(props);
</script>

<template>
    <AppLayout :title="t('income_expenses.title')">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <PageHeader
                :title="t('income_expenses.title')"
                :subtitle="
                    t('income_expenses.subtitle', {
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
                </template>
                <template #actions>
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
                        <Link
                            v-if="financial_report"
                            :href="
                                route(
                                    'income-expenses.recurring-expenses.index',
                                    {
                                        year: filters.year,
                                        month: filters.month,
                                        store_id: active_store?.id ?? null,
                                    },
                                )
                            "
                        >
                            <Button variant="secondary">
                                <CalendarClock :size="15" />{{
                                    t('income_expenses.recurring.manage')
                                }}
                            </Button>
                        </Link>
                        <template
                            v-if="
                                active_store?.is_active &&
                                financial_report?.report.status === 'open'
                            "
                        >
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
                            v-if="
                                active_store?.is_active &&
                                financial_report?.report.status === 'closed'
                            "
                            variant="secondary"
                            :disabled="lifecycleProcessing"
                            @click="lifecycle('reopen')"
                        >
                            <UnlockKeyhole :size="15" />{{
                                t('income_expenses.reopen')
                            }}
                        </Button>
                    </div>
                </template>
            </PageHeader>

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
                    v-for="section in financialSections"
                    :key="section.key"
                    class="space-y-4"
                >
                    <div class="flex items-center justify-between px-1">
                        <h2 class="font-heading text-lg font-bold">
                            {{ t(`income_expenses.sections.${section.key}`) }}
                        </h2>
                        <Button
                            v-if="
                                active_store?.is_active &&
                                financial_report.report.status === 'open'
                            "
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
                                            active_store?.is_active &&
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
                        <tfoot>
                            <tr
                                :data-testid="`financial-totals-${section.key}`"
                            >
                                <th
                                    colspan="3"
                                    data-label=""
                                    data-mobile-hidden
                                    class="text-left text-xs font-semibold text-on-surface-variant"
                                >
                                    Σ
                                </th>
                                <td
                                    class="text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ money(section.calculatedTotal) }}
                                </td>
                                <td
                                    class="text-right text-xs font-semibold text-on-surface"
                                >
                                    {{ money(section.effectiveTotal) }}
                                </td>
                                <td data-label="" data-mobile-hidden></td>
                            </tr>
                        </tfoot>
                    </DataTable>
                </section>
            </template>
        </div>

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
