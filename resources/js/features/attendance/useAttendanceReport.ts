import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import { withActionErrorToast } from '@/lib/action-errors';

type Worker = { id: number; first_name: string; last_name: string };

type BreakRow = {
    started_at: string;
    ended_at: string | null;
    seconds: number;
};

type SessionRow = {
    id: number;
    worker_id: number;
    worker_name: string;
    worker_color: string;
    date: string;
    started_at: string;
    ended_at: string | null;
    breaks: BreakRow[];
    break_seconds: number;
    actual_seconds: number | null;
    planned_seconds: number | null;
    difference_seconds: number | null;
    wage: number | null;
    voided: boolean;
};

type DeviationStatus = 'pending' | 'approved' | 'rejected';

type DeviationRow = {
    shift_id: number;
    primary_session_id: number;
    status: DeviationStatus;
    planned_start_time: string;
    planned_end_time: string;
    actual_started_at: string;
    actual_ended_at: string;
    arrival_offset_seconds: number;
    departure_offset_seconds: number;
    can_approve: boolean;
    reason: string | null;
    reviewed_at: string | null;
};

type SummaryRow = {
    actual_seconds: number;
    planned_seconds: number;
    wage: number;
};
export type AttendanceReportProps = {
    store: { id: number; name: string; is_active: boolean } | null;
    workers: Worker[];
    active_workers: Worker[];
    filters: { month: string; worker_id: number | null } | null;
    report: {
        month: string;
        rows: SessionRow[];
        summary: SummaryRow[];
        deviations: DeviationRow[];
    } | null;
};

export function useAttendanceReport(props: AttendanceReportProps) {
    const { t, locale } = useI18n();

    const route = useRoute();

    const dialog = useDialog();

    const reportMonth = ref(props.filters?.month ?? '');

    const reportWorkerId = ref(
        props.filters?.worker_id === null ||
            props.filters?.worker_id === undefined
            ? ''
            : String(props.filters.worker_id),
    );

    const correctionOpen = ref(false);

    const editingSessionId = ref<number | null>(null);

    const correctionForm = useForm({
        store_id: props.store?.id ?? null,
        worker_id: '',
        started_at: '',
        ended_at: '',
        breaks: [] as Array<{ started_at: string; ended_at: string }>,
        reason: '',
    });

    const reviewOpen = ref(false);

    const activeDeviation = ref<DeviationRow | null>(null);

    const reviewForm = useForm({
        store_id: props.store?.id ?? null,
        decision: 'approved' as 'approved' | 'rejected',
        reason: '',
        start_time: '',
        end_time: '',
        allow_overlap: false,
        expected_started_at: '',
        expected_ended_at: '',
        expected_start_time: '',
        expected_end_time: '',
    });

    const reviewErrors = computed(
        () => reviewForm.errors as Record<string, string | undefined>,
    );

    const timeSelectOptions = Array.from({ length: 96 }, (_, index) => {
        const minutes = index * 15;
        const time = `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
        return { value: time, label: time };
    });

    const deviationsBySession = computed(
        () =>
            new Map(
                (props.report?.deviations ?? []).map((deviation) => [
                    deviation.primary_session_id,
                    deviation,
                ]),
            ),
    );

    const correctionWorkerOptions = computed(() => {
        const workers = [...props.active_workers];
        if (editingSessionId.value !== null) {
            const selected = props.workers.find(
                (worker) => String(worker.id) === correctionForm.worker_id,
            );
            if (
                selected &&
                !workers.some((worker) => worker.id === selected.id)
            ) {
                workers.push(selected);
            }
        }

        return workers.map((worker) => ({
            value: String(worker.id),
            label: `${worker.first_name} ${worker.last_name}`,
        }));
    });

    const reportTotals = computed(() =>
        (props.report?.summary ?? []).reduce(
            (total, row) => ({
                actual: total.actual + row.actual_seconds,
                planned: total.planned + row.planned_seconds,
                wage: total.wage + row.wage,
            }),
            { actual: 0, planned: 0, wage: 0 },
        ),
    );

    function timeOnly(value: string | null): string {
        if (value === null) return t('attendance.now');
        return new Intl.DateTimeFormat(locale.value, {
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    }

    function localInput(value: string | null): string {
        if (value === null) return '';
        const date = new Date(value);
        const offset = date.getTimezoneOffset() * 60_000;
        return new Date(date.getTime() - offset).toISOString().slice(0, 16);
    }

    function duration(seconds: number | null | undefined): string {
        if (seconds === null || seconds === undefined) return '—';
        const sign = seconds < 0 ? '−' : '';
        const minutes = Math.round(Math.abs(seconds) / 60);
        return `${sign}${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;
    }

    function roundedQuarter(value: string): string {
        const parts = new Intl.DateTimeFormat('en-GB', {
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
            timeZone: 'Europe/Prague',
        }).formatToParts(new Date(value));
        const hour = Number(
            parts.find((part) => part.type === 'hour')?.value ?? 0,
        );
        const minute = Number(
            parts.find((part) => part.type === 'minute')?.value ?? 0,
        );
        const slot = Math.min(95, Math.floor((hour * 60 + minute + 7) / 15));
        return timeSelectOptions[slot]?.value ?? '00:00';
    }

    function openReview(deviation: DeviationRow): void {
        activeDeviation.value = deviation;
        reviewForm.reset();
        reviewForm.clearErrors();
        reviewForm.decision = 'approved';
        reviewForm.reason = '';
        reviewForm.start_time = roundedQuarter(deviation.actual_started_at);
        reviewForm.end_time = roundedQuarter(deviation.actual_ended_at);
        reviewForm.allow_overlap = false;
        reviewForm.expected_started_at = deviation.actual_started_at;
        reviewForm.expected_ended_at = deviation.actual_ended_at;
        reviewForm.expected_start_time = deviation.planned_start_time;
        reviewForm.expected_end_time = deviation.planned_end_time;
        reviewOpen.value = true;
    }

    function closeReview(): void {
        reviewOpen.value = false;
        activeDeviation.value = null;
        reviewForm.reset();
    }

    function submitReview(decision: 'approved' | 'rejected'): void {
        if (activeDeviation.value === null) return;
        reviewForm.decision = decision;
        const confirmOverlap = async (
            errors: Record<string, string>,
        ): Promise<void> => {
            if (
                errors.overlap !== undefined &&
                !reviewForm.allow_overlap &&
                (await dialog.confirm({
                    title: t('common.confirm'),
                    message: t('shifts.overlap_confirm'),
                    confirmLabel: t('common.continue'),
                    variant: 'warning',
                }))
            ) {
                reviewForm.allow_overlap = true;
                submitReview(decision);
            }
        };
        reviewForm.post(
            route('attendance.deviation-reviews.store', {
                shift: activeDeviation.value.shift_id,
            }),
            {
                preserveScroll: true,
                onError: (errors) => void confirmOverlap(errors),
                onSuccess: closeReview,
            },
        );
    }

    function applyFilters(): void {
        router.get(
            route('attendance.report'),
            {
                store_id: props.store?.id ?? null,
                month: reportMonth.value,
                worker_id: reportWorkerId.value || null,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function openCreate(): void {
        editingSessionId.value = null;
        correctionForm.reset();
        correctionForm.breaks = [];
        correctionOpen.value = true;
    }

    function openEdit(row: SessionRow): void {
        editingSessionId.value = row.id;
        correctionForm.worker_id = String(row.worker_id);
        correctionForm.started_at = localInput(row.started_at);
        correctionForm.ended_at = localInput(row.ended_at);
        correctionForm.breaks = row.breaks.map((item) => ({
            started_at: localInput(item.started_at),
            ended_at: localInput(item.ended_at),
        }));
        correctionForm.reason = '';
        correctionOpen.value = true;
    }

    function saveCorrection(): void {
        if (editingSessionId.value === null) {
            correctionForm.post(route('attendance.corrections.store'), {
                onSuccess: () => (correctionOpen.value = false),
            });
            return;
        }
        correctionForm.put(
            route('attendance.sessions.update', editingSessionId.value),
            { onSuccess: () => (correctionOpen.value = false) },
        );
    }

    async function voidSession(id: number): Promise<void> {
        const reason = await dialog.prompt({
            title: t('attendance.correction.void'),
            message: t('attendance.correction.reason_prompt'),
            label: t('common.reason'),
            confirmLabel: t('attendance.correction.void'),
            variant: 'danger',
            required: true,
        });
        if (reason?.trim()) {
            router.post(
                route('attendance.sessions.void', id),
                { store_id: props.store?.id ?? null, reason: reason.trim() },
                withActionErrorToast(),
            );
        }
    }

    function addBreak(): void {
        correctionForm.breaks.push({ started_at: '', ended_at: '' });
    }

    function removeBreak(index: number): void {
        correctionForm.breaks.splice(index, 1);
    }
    return {
        t,
        route,
        reportMonth,
        reportWorkerId,
        correctionOpen,
        editingSessionId,
        correctionForm,
        reviewOpen,
        activeDeviation,
        reviewForm,
        reviewErrors,
        timeSelectOptions,
        deviationsBySession,
        correctionWorkerOptions,
        reportTotals,
        timeOnly,
        duration,
        openReview,
        closeReview,
        submitReview,
        applyFilters,
        openCreate,
        openEdit,
        saveCorrection,
        voidSession,
        addBreak,
        removeBreak,
    };
}
