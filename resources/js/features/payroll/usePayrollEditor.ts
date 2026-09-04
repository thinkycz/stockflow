import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';
import type { PayrollAdjustment, Payslip } from '@/features/payroll/types';

export type PayrollEditorProps = {
    active_store: {
        id: number;
        name: string;
        is_warehouse: boolean;
        is_active: boolean;
    };
    filters: { year: number; month: number };
    report: {
        id: number | null;
        status: 'open' | 'closed';
        closed_at: string | null;
        reopened_at: string | null;
    };
    payslip: Payslip;
};

export function usePayrollEditor(props: PayrollEditorProps) {
    const { t, locale } = useI18n();

    const route = useRoute();

    const dialog = useDialog();

    const adjustmentModalOpen = ref(false);

    const wageModalOpen = ref(false);

    const editingAdjustment = ref<PayrollAdjustment | null>(null);

    const adjustmentForm = useForm({
        store_id: props.active_store.id,
        year: props.filters.year,
        month: props.filters.month,
        worker_id: props.payslip.worker_id,
        type: 'tip' as 'tip' | 'deduction',
        amount: '',
        reason: '',
    });

    const wageForm = useForm({
        store_id: props.active_store.id,
        year: props.filters.year,
        month: props.filters.month,
        worker_id: props.payslip.worker_id,
        hours: '',
        hourly_rate: '',
    });

    function formatMonth(): string {
        return new Intl.DateTimeFormat(locale.value, {
            year: 'numeric',
            month: 'long',
        }).format(new Date(props.filters.year, props.filters.month - 1, 1));
    }

    function date(value: string): string {
        return new Intl.DateTimeFormat(locale.value).format(
            new Date(`${value}T12:00:00`),
        );
    }

    function time(value: string | null): string {
        if (value === null) return '—';
        return new Intl.DateTimeFormat(locale.value, {
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    }

    function duration(seconds: number | null): string {
        if (seconds === null) return '—';
        const minutes = Math.round(Math.abs(seconds) / 60);
        return `${seconds < 0 ? '−' : ''}${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;
    }

    function hours(value: number): string {
        return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(value)} h`;
    }

    function openAdjustment(adjustment: PayrollAdjustment | null = null): void {
        editingAdjustment.value = adjustment;
        adjustmentForm.clearErrors();
        adjustmentForm.type = adjustment?.type ?? 'tip';
        adjustmentForm.amount = adjustment ? String(adjustment.amount) : '';
        adjustmentForm.reason = adjustment?.reason ?? '';
        adjustmentModalOpen.value = true;
    }

    function submitAdjustment(): void {
        const options = {
            preserveScroll: true,
            onSuccess: () => (adjustmentModalOpen.value = false),
        };
        if (editingAdjustment.value !== null) {
            adjustmentForm.put(
                route('payroll.adjustments.update', editingAdjustment.value.id),
                options,
            );
            return;
        }
        adjustmentForm.post(route('payroll.adjustments.store'), options);
    }

    async function deleteAdjustment(
        adjustment: PayrollAdjustment,
    ): Promise<void> {
        if (
            !(await dialog.confirm({
                title: `${t('common.delete')}: ${adjustment.reason}`,
                message: t('payroll.confirm_delete_adjustment'),
                confirmLabel: t('common.delete'),
                variant: 'danger',
            }))
        )
            return;

        router.delete(
            route('payroll.adjustments.destroy', adjustment.id),
            withActionErrorToast({
                data: {
                    store_id: props.active_store.id,
                    year: props.filters.year,
                    month: props.filters.month,
                },
                preserveScroll: true,
            }),
        );
    }

    function openWageOverride(): void {
        wageForm.clearErrors();
        wageForm.hours = String(props.payslip.payable_hours);
        wageForm.hourly_rate = String(props.payslip.payable_hourly_rate);
        wageModalOpen.value = true;
    }

    function submitWageOverride(): void {
        wageForm.put(route('payroll.wage-override.update'), {
            preserveScroll: true,
            onSuccess: () => (wageModalOpen.value = false),
        });
    }

    async function resetWageOverride(): Promise<void> {
        if (
            !(await dialog.confirm({
                title: props.payslip.worker_name,
                message: t('payroll.confirm_reset_wage'),
                confirmLabel: t('common.confirm'),
                variant: 'warning',
            }))
        )
            return;

        router.delete(
            route('payroll.wage-override.destroy'),
            withActionErrorToast({
                data: {
                    store_id: props.active_store.id,
                    year: props.filters.year,
                    month: props.filters.month,
                    worker_id: props.payslip.worker_id,
                },
                preserveScroll: true,
                onSuccess: () => (wageModalOpen.value = false),
            }),
        );
    }

    async function removeWorker(): Promise<void> {
        if (
            !(await dialog.confirm({
                title: props.payslip.worker_name,
                message: t('payroll.confirm_remove_worker'),
                confirmLabel: t('payroll.remove_worker'),
                variant: 'danger',
            }))
        )
            return;

        router.delete(
            route('payroll.workers.destroy', {
                worker: props.payslip.worker_id,
                store_id: props.active_store.id,
            }),
            withActionErrorToast({
                data: {
                    store_id: props.active_store.id,
                    year: props.filters.year,
                    month: props.filters.month,
                },
            }),
        );
    }
    return {
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
    };
}
