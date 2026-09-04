import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';
import type { PayrollReport } from '@/features/payroll/types';

export type PayrollOverviewProps = {
    active_store: {
        id: number;
        name: string;
        is_warehouse: boolean;
        is_active: boolean;
    } | null;
    filters: { year: number; month: number };
    payroll_report: PayrollReport | null;
    available_workers: { id: number; title: string }[];
};

export function usePayrollOverview(props: PayrollOverviewProps) {
    const { t, locale } = useI18n();

    const route = useRoute();

    const dialog = useDialog();

    const lifecycleProcessing = ref(false);

    const workerModalOpen = ref(false);

    const tipModalOpen = ref(false);

    const workerForm = useForm({
        store_id: props.active_store?.id ?? null,
        year: props.filters.year,
        month: props.filters.month,
        worker_id: null as number | null,
    });

    const tipForm = useForm({
        store_id: props.active_store?.id ?? null,
        year: props.filters.year,
        month: props.filters.month,
        amount: '',
    });

    const tipEligiblePayslips = computed(
        () =>
            props.payroll_report?.payslips.filter(
                (payslip) => payslip.payable_hours > 0,
            ) ?? [],
    );

    const tipEligibleHours = computed(() =>
        tipEligiblePayslips.value.reduce(
            (total, payslip) => total + payslip.payable_hours,
            0,
        ),
    );

    const payrollTotals = computed(() =>
        (props.payroll_report?.payslips ?? []).reduce(
            (totals, payslip) => ({
                payable_hours: totals.payable_hours + payslip.payable_hours,
                base_amount: totals.base_amount + payslip.base_amount,
                tip_amount: totals.tip_amount + payslip.tip_amount,
                deduction_amount:
                    totals.deduction_amount + payslip.deduction_amount,
                final_amount: totals.final_amount + payslip.final_amount,
            }),
            {
                payable_hours: 0,
                base_amount: 0,
                tip_amount: 0,
                deduction_amount: 0,
                final_amount: 0,
            },
        ),
    );

    function openWorkerModal(): void {
        workerForm.clearErrors();
        workerForm.year = props.filters.year;
        workerForm.month = props.filters.month;
        workerForm.worker_id = null;
        workerModalOpen.value = true;
    }

    function submitWorker(): void {
        workerForm.post(
            route('payroll.workers.store', {
                store_id: props.active_store?.id ?? null,
            }),
            {
                preserveScroll: true,
                onSuccess: () => (workerModalOpen.value = false),
            },
        );
    }

    function openTipModal(): void {
        tipForm.clearErrors();
        tipForm.year = props.filters.year;
        tipForm.month = props.filters.month;
        tipForm.amount = '';
        tipModalOpen.value = true;
    }

    function submitTips(): void {
        tipForm.post(
            route('payroll.tip-distributions.store', {
                store_id: props.active_store?.id ?? null,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    tipModalOpen.value = false;
                    tipForm.reset('amount');
                },
            },
        );
    }

    function monthValue(): string {
        return `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}`;
    }

    function changeMonth(value: string): void {
        const [year, month] = value.split('-').map(Number);
        if (!year || !month) return;
        router.get(
            route('payroll.index'),
            { year, month, store_id: props.active_store?.id ?? null },
            { preserveState: true },
        );
    }

    function hours(value: number): string {
        return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(value)} h`;
    }

    async function lifecycle(action: 'close' | 'reopen'): Promise<void> {
        if (
            !(await dialog.confirm({
                title: t(`payroll.${action}`),
                message: t(`payroll.confirm_${action}`),
                confirmLabel: t(`payroll.${action}`),
                variant: action === 'close' ? 'warning' : 'default',
            }))
        )
            return;

        lifecycleProcessing.value = true;
        router.post(
            route(`payroll.${action}`),
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
    return {
        t,
        route,
        lifecycleProcessing,
        workerModalOpen,
        tipModalOpen,
        workerForm,
        tipForm,
        tipEligiblePayslips,
        tipEligibleHours,
        payrollTotals,
        openWorkerModal,
        submitWorker,
        openTipModal,
        submitTips,
        monthValue,
        changeMonth,
        hours,
        lifecycle,
    };
}
