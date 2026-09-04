import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import { withActionErrorToast } from '@/lib/action-errors';

type FinancialRow = {
    id: string;
    manual_row_id?: number;
    kind: 'automatic' | 'manual';
    direction: 'income' | 'expense';
    source_type:
        | 'revenue'
        | 'stock_movement'
        | 'wage'
        | 'recurring_expense'
        | null;
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

type FinancialSection = {
    key: 'income' | 'expense';
    rows: FinancialRow[];
    calculatedTotal: number;
    effectiveTotal: number;
};
export type FinanceEntriesProps = {
    active_store: {
        id: number;
        name: string;
        is_warehouse: boolean;
        is_active: boolean;
    } | null;
    filters: { year: number; month: number };
    financial_report: FinancialReport | null;
};

export function useFinanceEntries(props: FinanceEntriesProps) {
    const { t, locale } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const dialog = useDialog();

    const manualModalOpen = ref(false);

    const editingManualRow = ref<FinancialRow | null>(null);

    const overrideModalOpen = ref(false);

    const overridingRow = ref<FinancialRow | null>(null);

    const lifecycleProcessing = ref(false);

    const financialSections = computed<FinancialSection[]>(() => {
        if (props.financial_report === null) return [];

        return [
            {
                key: 'income' as const,
                rows: props.financial_report.income_rows,
            },
            {
                key: 'expense' as const,
                rows: props.financial_report.expense_rows,
            },
        ].map((section) => ({
            ...section,
            calculatedTotal: section.rows.reduce(
                (total, row) => total + row.calculated_amount,
                0,
            ),
            effectiveTotal: section.rows.reduce(
                (total, row) => total + row.effective_amount,
                0,
            ),
        }));
    });

    const manualForm = useForm({
        store_id: props.active_store?.id ?? null,
        year: props.filters.year,
        month: props.filters.month,
        direction: 'expense' as 'income' | 'expense',
        label: '',
        occurred_on: `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}-01`,
        amount: '',
        note: '',
    });

    const overrideForm = useForm({
        store_id: props.active_store?.id ?? null,
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

    function changeMonth(value: string): void {
        const [year, month] = value.split('-').map(Number);
        if (year && month) {
            router.get(
                route('income-expenses.index'),
                { year, month, store_id: props.active_store?.id ?? null },
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
            withActionErrorToast({
                data: {
                    store_id: props.active_store?.id ?? null,
                    year: props.filters.year,
                    month: props.filters.month,
                },
            }),
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
        router.delete(
            route('income-expenses.overrides.destroy'),
            withActionErrorToast({
                data: {
                    store_id: props.active_store?.id ?? null,
                    year: props.filters.year,
                    month: props.filters.month,
                    source_type: row.source_type,
                    source_key: row.source_key,
                },
            }),
        );
    }

    async function lifecycle(action: 'close' | 'reopen'): Promise<void> {
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

    function rowSecondary(row: FinancialRow): string | null {
        if (row.source_type === 'stock_movement') {
            const source =
                row.details.movement_type === 'incoming'
                    ? t('stock_movements.types.incoming')
                    : String(row.details.source_store_name ?? '—');
            const destination = String(
                row.details.destination_store_name ??
                    props.active_store?.name ??
                    '—',
            );

            return `${source} → ${destination}`;
        }
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
            return route(
                'stock-movements.show',
                Number(row.details.movement_id),
            );
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
    return {
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
    };
}
