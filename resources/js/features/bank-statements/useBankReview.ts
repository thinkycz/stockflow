import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { useSharedProps } from '@/composables/useSharedProps';
import { sameTransaction, restoreReviewDraft } from './review-draft';
import { withActionErrorToast } from '@/lib/action-errors';

export type Transaction = {
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

export type PeriodCandidate = {
    from: string;
    to: string;
    expected: string | null;
    difference: string | null;
    tolerance: string | null;
    within_tolerance: boolean;
    reason: string | null;
    source: 'explicit' | 'inferred';
};

type ReconciliationRow = {
    transaction_id: number;
    status: string;
    actual: string;
    expected: string | null;
    difference: string | null;
    reason: string | null;
    pairing: 'paired' | 'unresolved' | 'excluded';
    amount_check: 'within_tolerance' | 'difference' | 'not_checked';
    tolerance: string | null;
    candidates: PeriodCandidate[];
    automatic: PeriodCandidate | null;
    discovery_reason: string | null;
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
        paired_count: number;
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

    const { errors: pageErrors, user } = useSharedProps();

    const filter = ref('all');

    const resultFilter = ref('all');

    const form = useForm<{ transactions: Transaction[] }>({
        transactions: props.transactions.map((row) => ({ ...row })),
    });
    form.defaults({
        transactions: props.transactions.map((row) => ({ ...row })),
    });
    const savedRows = ref(props.transactions.map((row) => ({ ...row })));
    const automaticSources = ref<Record<number, PeriodCandidate>>({});

    function synchronize(force = false): void {
        if (!force && form.isDirty && props.statement.editable) return;
        savedRows.value = props.transactions.map((row) => ({ ...row }));
        form.transactions = savedRows.value.map((row) => ({ ...row }));
        form.defaults({
            transactions: savedRows.value.map((row) => ({ ...row })),
        });
        automaticSources.value = {};
        populateAutomaticPeriods();
    }

    function populateAutomaticPeriods(): void {
        if (!props.statement.editable) return;
        for (const row of form.transactions) {
            const candidate = props.reconciliation.rows.find(
                (result) => result.transaction_id === row.id,
            )?.automatic;
            if (
                candidate &&
                !row.sales_from &&
                !row.sales_to &&
                !row.manually_edited &&
                !isPending(row)
            ) {
                applyCandidate(row, candidate);
                if (row.id) automaticSources.value[row.id] = candidate;
            }
        }
    }

    function applyCandidate(
        row: Transaction,
        candidate: PeriodCandidate,
    ): void {
        if (!props.statement.editable || candidate.reason) return;
        row.sales_from = candidate.from;
        row.sales_to = candidate.to;
    }

    function automaticSource(row: Transaction): string | null {
        const candidate = row.id ? automaticSources.value[row.id] : null;
        return candidate &&
            candidate.from === row.sales_from &&
            candidate.to === row.sales_to
            ? candidate.source
            : null;
    }

    function isPending(row: Transaction): boolean {
        return !sameTransaction(
            row,
            savedRows.value.find((saved) => saved.id === row.id),
        );
    }

    function candidatesFor(row: Transaction): PeriodCandidate[] {
        if (isPending(row)) return [];
        return (
            props.reconciliation.rows.find(
                (result) => result.transaction_id === row.id,
            )?.candidates ?? []
        );
    }

    function reasonFor(row: Transaction): string | null {
        if (isPending(row)) return null;
        const result = resultFor(row);
        return result?.discovery_reason ?? result?.reason ?? null;
    }

    const draftKey = `bank-review-${user.value?.id}-${props.statement.id}`;
    let draftReady = false;
    watch(
        () => form.transactions,
        () => {
            if (!draftReady || !props.statement.editable) return;
            try {
                if (
                    form.transactions.length === savedRows.value.length &&
                    form.transactions.every((row) => !isPending(row))
                ) {
                    sessionStorage.removeItem(draftKey);
                } else {
                    sessionStorage.setItem(
                        draftKey,
                        JSON.stringify({
                            baseline: JSON.stringify(savedRows.value),
                            rows: form.transactions,
                        }),
                    );
                }
            } catch {
                /* The form remains usable when browser storage is unavailable. */
            }
        },
        { deep: true },
    );

    let polling: ReturnType<typeof setInterval> | null = null;

    watch(
        () => props.transactions,
        () => synchronize(),
    );

    watch(
        () => props.statement.terminal,
        (terminal) => {
            if (terminal) stopPolling();
        },
    );

    onMounted(() => {
        try {
            if (props.statement.editable) {
                const restored = restoreReviewDraft(
                    sessionStorage.getItem(draftKey),
                    savedRows.value,
                );
                if (restored) form.transactions = restored;
            } else sessionStorage.removeItem(draftKey);
        } catch {
            /* Browser storage is optional. */
        }
        draftReady = true;
        if (!props.statement.editable) synchronize(true);
        else populateAutomaticPeriods();
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
                    (resultFilter.value === 'paired' &&
                        resultFor(transaction)?.pairing === 'paired') ||
                    (isPending(transaction)
                        ? 'pending'
                        : (resultFor(transaction)?.status ?? 'unresolved')) ===
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
        { value: 'paired', label: t('bank_statements.result.paired') },
        { value: 'pending', label: t('bank_statements.result.pending') },
        { value: 'matched', label: t('bank_statements.result.matched') },
        { value: 'mismatch', label: t('bank_statements.result.mismatch') },
        { value: 'unresolved', label: t('bank_statements.result.unresolved') },
        { value: 'excluded', label: t('bank_statements.result.excluded') },
    ]);

    const confirmationReasons = computed(() => {
        const reasons: string[] = [];
        if (form.isDirty) reasons.push(t('bank_statements.blockers.unsaved'));
        if (props.statement.parse_warnings.length > 0)
            reasons.push(t('bank_statements.integrity_blocked'));
        if (
            form.transactions.some(
                (row) =>
                    ['card', 'wolt', 'bolt', 'foodora'].includes(
                        row.category,
                    ) &&
                    (!row.sales_from ||
                        !row.sales_to ||
                        row.sales_from > row.sales_to),
            )
        ) {
            reasons.push(t('bank_statements.blockers.periods'));
        }
        return reasons;
    });
    const confirmationBlocked = computed(
        () => form.processing || confirmationReasons.value.length > 0,
    );
    const reviewCounts = computed(() => {
        const counts = {
            paired: 0,
            matched: 0,
            mismatch: 0,
            unresolved: 0,
            excluded: 0,
            pending: 0,
        };
        for (const row of form.transactions) {
            if (isPending(row)) {
                counts.pending++;
                continue;
            }
            const result = resultFor(row);
            if (result?.pairing === 'paired') counts.paired++;
            const status = result?.status ?? 'unresolved';
            if (
                status === 'matched' ||
                status === 'mismatch' ||
                status === 'excluded' ||
                status === 'unresolved'
            )
                counts[status]++;
        }
        return counts;
    });

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
                onSuccess: () => {
                    synchronize(true);
                    try {
                        sessionStorage.removeItem(draftKey);
                    } catch {
                        /* Browser storage is optional. */
                    }
                },
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
        if (isPending(transaction)) return null;
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
        confirmationReasons,
        reviewCounts,
        isPending,
        applyCandidate,
        candidatesFor,
        reasonFor,
        automaticSource,
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
