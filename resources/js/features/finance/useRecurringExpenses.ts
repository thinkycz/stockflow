import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

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
export type RecurringExpensesProps = {
    active_store: { id: number; name: string; is_warehouse: boolean } | null;
    filters: { year: number; month: number };
    recurring_expenses: RecurringExpense[];
};

export function useRecurringExpenses(props: RecurringExpensesProps) {
    const { t, locale } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const editorMode = ref<'create' | 'change' | 'terminate'>('create');

    const selectedExpense = ref<RecurringExpense | null>(null);

    const statuses = ['active', 'upcoming', 'ended'] as const;

    const expenseForm = useForm({
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

    const statusCounts = computed(() => ({
        active: props.recurring_expenses.filter(
            (expense) => expense.status === 'active',
        ).length,
        upcoming: props.recurring_expenses.filter(
            (expense) => expense.status === 'upcoming',
        ).length,
        ended: props.recurring_expenses.filter(
            (expense) => expense.status === 'ended',
        ).length,
    }));

    function selectedPeriod(): string {
        return `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}`;
    }

    function followingPeriod(value: string): string {
        const [year, month] = value.split('-').map(Number);
        const next = new Date(Date.UTC(year, month, 1));
        return `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}`;
    }

    function revealEditor(): void {
        void nextTick(() => {
            const editor = document.getElementById('recurring-expense-editor');
            editor?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            editor
                ?.querySelector<HTMLInputElement>('input')
                ?.focus({ preventScroll: true });
        });
    }

    function startCreate(): void {
        selectedExpense.value = null;
        editorMode.value = 'create';
        expenseForm.clearErrors();
        expenseForm.reset();
        expenseForm.year = props.filters.year;
        expenseForm.month = props.filters.month;
        expenseForm.effective_period = selectedPeriod();
        expenseForm.due_day = '1';
        revealEditor();
    }

    function startChange(expense: RecurringExpense): void {
        selectedExpense.value = expense;
        editorMode.value = 'change';
        expenseForm.clearErrors();
        expenseForm.year = props.filters.year;
        expenseForm.month = props.filters.month;
        expenseForm.label = expense.label;
        expenseForm.amount = String(expense.amount);
        expenseForm.due_day = String(expense.due_day);
        expenseForm.note = expense.note ?? '';
        expenseForm.effective_period =
            expense.starts_on > selectedPeriod()
                ? expense.starts_on
                : selectedPeriod();
        revealEditor();
    }

    function startTermination(expense: RecurringExpense): void {
        selectedExpense.value = expense;
        editorMode.value = 'terminate';
        terminationForm.clearErrors();
        terminationForm.year = props.filters.year;
        terminationForm.month = props.filters.month;
        terminationForm.ends_before_period = followingPeriod(
            expense.starts_on > selectedPeriod()
                ? expense.starts_on
                : selectedPeriod(),
        );
        revealEditor();
    }

    function submitExpense(): void {
        const options = { onSuccess: startCreate };
        if (editorMode.value === 'change' && selectedExpense.value) {
            expenseForm.put(
                route(
                    'income-expenses.recurring-expenses.update',
                    selectedExpense.value.id,
                ),
                options,
            );
            return;
        }
        expenseForm.post(
            route('income-expenses.recurring-expenses.store'),
            options,
        );
    }

    function submitTermination(): void {
        if (!selectedExpense.value) return;
        terminationForm.post(
            route(
                'income-expenses.recurring-expenses.terminate',
                selectedExpense.value.id,
            ),
            { onSuccess: startCreate },
        );
    }

    function changeMonth(value: string): void {
        const [year, month] = value.split('-').map(Number);
        if (!year || !month) return;
        router.get(
            route('income-expenses.recurring-expenses.index'),
            { year, month },
            { preserveState: true },
        );
    }

    function money(value: number): string {
        return new Intl.NumberFormat(locale.value, {
            style: 'currency',
            currency: 'CZK',
            minimumFractionDigits: 2,
        }).format(value);
    }

    function period(value: string): string {
        return new Intl.DateTimeFormat(locale.value, {
            month: 'long',
            year: 'numeric',
        }).format(new Date(`${value.slice(0, 7)}-01T12:00:00`));
    }
    return {
        t,
        route,
        editorMode,
        selectedExpense,
        statuses,
        expenseForm,
        terminationForm,
        statusCounts,
        selectedPeriod,
        startCreate,
        startChange,
        startTermination,
        submitExpense,
        submitTermination,
        changeMonth,
        money,
        period,
    };
}
