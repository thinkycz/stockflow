import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { useSharedProps } from '@/composables/useSharedProps';
import { withActionErrorToast } from '@/lib/action-errors';

type Transaction = {
    id?: number;
    booked_on: string;
    executed_on: string | null;
    item_type: string;
    amount: string;
    currency: string;
    counterparty_name: string | null;
    counterparty_account: string | null;
    variable_symbol: string | null;
    constant_symbol: string | null;
    specific_symbol: string | null;
    description: string | null;
    category: string;
    sales_from: string | null;
    sales_to: string | null;
    review_note: string | null;
    manually_edited: boolean;
};

type ReconciliationRow = {
    transaction_id: number;
    status: string;
    actual: string;
    expected: string | null;
    difference: string | null;
    reason: string | null;
};
export type BankReviewProps = {
    statement: {
        id: number;
        status: string;
        bank_name: string | null;
        statement_number: string | null;
        period_from: string | null;
        period_to: string | null;
        original_name: string;
        store_name: string;
        store_active: boolean;
        account_number: string | null;
        iban: string | null;
        opening_balance: string | null;
        total_credits: string | null;
        total_debits: string | null;
        closing_balance: string | null;
        credit_count: number | null;
        debit_count: number | null;
        parse_warnings: string[];
        last_error: string | null;
        attempt_count: number;
        editable: boolean;
        terminal: boolean;
    };
    transactions: Transaction[];
    reconciliation: {
        counts: Record<
            'matched' | 'mismatch' | 'unresolved' | 'excluded',
            number
        >;
        rows: ReconciliationRow[];
    };
};

export function useBankReview(props: BankReviewProps) {
    const { t } = useI18n();

    const route = useRoute();

    const dialog = useDialog();

    const { errors: pageErrors } = useSharedProps();

    const filter = ref('all');

    const resultFilter = ref('all');

    const form = useForm<{ transactions: Transaction[] }>({
        transactions: props.transactions.map((row) => ({ ...row })),
    });

    let polling: ReturnType<typeof setInterval> | null = null;

    watch(
        () => props.transactions,
        (rows) => {
            form.transactions = rows.map((row) => ({ ...row }));
        },
    );

    watch(
        () => props.statement.terminal,
        (terminal) => {
            if (terminal) stopPolling();
        },
    );

    onMounted(() => {
        if (!props.statement.terminal) {
            polling = setInterval(() => {
                router.reload({
                    only: ['statement', 'transactions', 'reconciliation'],
                });
            }, 3000);
        }
    });

    onUnmounted(stopPolling);

    function stopPolling(): void {
        if (polling !== null) clearInterval(polling);
        polling = null;
    }

    const reconciliationById = computed(
        () =>
            new Map(
                props.reconciliation.rows.map((row) => [
                    row.transaction_id,
                    row,
                ]),
            ),
    );

    const visibleRows = computed(() =>
        form.transactions
            .map((transaction, index) => ({ transaction, index }))
            .filter(({ transaction }) => {
                const categoryMatches =
                    filter.value === 'all' ||
                    transaction.category === filter.value;
                const resultMatches =
                    resultFilter.value === 'all' ||
                    (resultFor(transaction)?.status ?? 'unresolved') ===
                        resultFilter.value;

                return categoryMatches && resultMatches;
            }),
    );

    const categoryOptions = computed(() => [
        { value: 'card', label: t('bank_statements.category.card') },
        { value: 'wolt', label: t('bank_statements.category.wolt') },
        { value: 'bolt', label: t('bank_statements.category.bolt') },
        { value: 'foodora', label: t('bank_statements.category.foodora') },
        {
            value: 'other_incoming',
            label: t('bank_statements.category.other_incoming'),
        },
        { value: 'outgoing', label: t('bank_statements.category.outgoing') },
    ]);

    const filterOptions = computed(() => [
        { value: 'all', label: t('bank_statements.filter.all') },
        ...categoryOptions.value,
    ]);

    const resultFilterOptions = computed(() => [
        { value: 'all', label: t('bank_statements.filter.all_results') },
        { value: 'matched', label: t('bank_statements.result.matched') },
        { value: 'mismatch', label: t('bank_statements.result.mismatch') },
        { value: 'unresolved', label: t('bank_statements.result.unresolved') },
        { value: 'excluded', label: t('bank_statements.result.excluded') },
    ]);

    const confirmationBlocked = computed(
        () =>
            form.isDirty ||
            props.statement.parse_warnings.length > 0 ||
            form.transactions.some(
                (transaction) =>
                    ['card', 'wolt', 'bolt', 'foodora'].includes(
                        transaction.category,
                    ) &&
                    (!transaction.sales_from || !transaction.sales_to),
            ),
    );

    const statementError = computed(() => errorFor('statement'));

    function errorFor(key: string): string | undefined {
        return (
            (form.errors as Record<string, string | undefined>)[key] ??
            pageErrors.value[key]
        );
    }

    function transactionError(
        index: number,
        field: keyof Transaction,
    ): string | undefined {
        return errorFor(`transactions.${index}.${field}`);
    }

    function addRow(): void {
        form.transactions.push({
            booked_on: props.statement.period_from ?? '',
            executed_on: null,
            item_type: '',
            amount: '0.00',
            currency: 'CZK',
            counterparty_name: null,
            counterparty_account: null,
            variable_symbol: null,
            constant_symbol: null,
            specific_symbol: null,
            description: null,
            category: 'other_incoming',
            sales_from: null,
            sales_to: null,
            review_note: null,
            manually_edited: true,
        });
    }

    function removeRow(index: number): void {
        form.transactions.splice(index, 1);
    }

    function save(): void {
        form.put(
            route('bank-statements.update', {
                bankStatement: props.statement.id,
            }),
            withActionErrorToast({
                preserveScroll: true,
            }),
        );
    }

    async function confirmStatement(): Promise<void> {
        if (
            !(await dialog.confirm({
                title: t('bank_statements.confirm.title'),
                message: t('bank_statements.confirm.message'),
                confirmLabel: t('bank_statements.actions.confirm'),
            }))
        )
            return;
        router.post(
            route('bank-statements.confirm', {
                bankStatement: props.statement.id,
            }),
            {},
            withActionErrorToast({ preserveScroll: true }),
        );
    }

    async function reopenStatement(): Promise<void> {
        if (
            !(await dialog.confirm({
                title: t('bank_statements.reopen.title'),
                message: t('bank_statements.reopen.message'),
                confirmLabel: t('bank_statements.actions.reopen'),
                variant: 'warning',
            }))
        )
            return;
        router.post(
            route('bank-statements.reopen', {
                bankStatement: props.statement.id,
            }),
            {},
            withActionErrorToast({ preserveScroll: true }),
        );
    }

    function retry(): void {
        router.post(
            route('bank-statements.retry', {
                bankStatement: props.statement.id,
            }),
            {},
            withActionErrorToast({ preserveScroll: true }),
        );
    }

    function resultFor(transaction: Transaction): ReconciliationRow | null {
        return transaction.id
            ? (reconciliationById.value.get(transaction.id) ?? null)
            : null;
    }

    function badgeVariant(
        status: string,
    ): 'neutral' | 'success' | 'warning' | 'danger' {
        if (status === 'confirmed' || status === 'matched') return 'success';
        if (status === 'failed' || status === 'mismatch') return 'danger';
        if (status === 'review' || status === 'unresolved') return 'warning';
        return 'neutral';
    }
    return {
        t,
        route,
        filter,
        resultFilter,
        form,
        visibleRows,
        categoryOptions,
        filterOptions,
        resultFilterOptions,
        confirmationBlocked,
        statementError,
        transactionError,
        addRow,
        removeRow,
        save,
        confirmStatement,
        reopenStatement,
        retry,
        resultFor,
        badgeVariant,
    };
}
