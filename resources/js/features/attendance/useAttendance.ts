import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';

type AttendanceStatus = 'absent' | 'present' | 'break' | 'stale';

type BreakRow = { started_at: string; ended_at: string | null };

type SessionRow = {
    id: number;
    started_at: string;
    ended_at: string | null;
    breaks: BreakRow[];
};

type AttendanceRow = {
    worker_id: number;
    worker_name: string;
    worker_color: string;
    status: AttendanceStatus;
    has_current_shift: boolean;
    shifts: Array<{ id: number; start_time: string; end_time: string }>;
    sessions: SessionRow[];
    quality: {
        attendance_rating_enabled: boolean;
        average_score: number | null;
        evaluated_shifts: number | null;
        band: 'good' | 'warning' | 'poor' | null;
    };
};
export type AttendanceProps = {
    store: { id: number; name: string; is_warehouse: boolean } | null;
    attendance_rows: AttendanceRow[];
    off_schedule_workers: Array<{ id: number; name: string }>;
    store_state: 'occupied' | 'empty' | 'unclear';
    is_admin: boolean;
};

export function useAttendance(props: AttendanceProps) {
    const { t, locale } = useI18n();

    const route = useRoute();

    const dialog = useDialog();

    const nowMs = ref(Date.now());

    const timerWorkerId = ref(
        String(
            props.attendance_rows.find((row) => row.status !== 'absent')
                ?.worker_id ??
                props.attendance_rows[0]?.worker_id ??
                props.off_schedule_workers[0]?.id ??
                '',
        ),
    );

    const pendingWorkerId = ref<number | null>(null);

    const actionForm = useForm({
        worker_id: '',
        action: '',
        confirm_without_shift: false,
    });

    const timerWorkerOptions = [
        ...props.attendance_rows.map((row) => ({
            value: String(row.worker_id),
            label: row.worker_name,
        })),
        ...props.off_schedule_workers.map((worker) => ({
            value: String(worker.id),
            label: worker.name,
        })),
    ].sort((left, right) =>
        left.label.localeCompare(right.label, locale.value),
    );

    const timerWorkerModel = computed({
        get: () => timerWorkerId.value,
        set: (value: string | number | null) => {
            if (value !== null && String(value) !== '')
                timerWorkerId.value = String(value);
        },
    });

    const timerRow = computed(() =>
        props.attendance_rows.find(
            (row) => row.worker_id === Number(timerWorkerId.value),
        ),
    );

    const timerSession = computed(() =>
        timerRow.value?.sessions.find((session) => session.ended_at === null),
    );

    const timerOpenBreak = computed(() =>
        timerSession.value?.breaks.find((pause) => pause.ended_at === null),
    );

    const timerSeconds = computed(() => {
        const row = timerRow.value;
        if (!row) return 0;
        if (row.status === 'break' && timerOpenBreak.value)
            return intervalSeconds(timerOpenBreak.value.started_at, null);
        return workedSeconds(row);
    });

    const timerStatus = computed<AttendanceStatus>(
        () => timerRow.value?.status ?? 'absent',
    );

    const timerHasShift = computed(
        () => timerRow.value?.has_current_shift === true,
    );

    function timeOnly(value: string | null): string {
        if (value === null) return t('attendance.now');
        return new Intl.DateTimeFormat(locale.value, {
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    }

    function intervalSeconds(start: string, end: string | null): number {
        return Math.max(
            0,
            Math.floor(
                ((end === null ? nowMs.value : Date.parse(end)) -
                    Date.parse(start)) /
                    1000,
            ),
        );
    }

    function breakSeconds(session: SessionRow): number {
        return session.breaks.reduce(
            (total, pause) =>
                total + intervalSeconds(pause.started_at, pause.ended_at),
            0,
        );
    }

    function workedSeconds(row: AttendanceRow): number {
        return row.sessions.reduce(
            (total, session) =>
                total +
                Math.max(
                    0,
                    intervalSeconds(session.started_at, session.ended_at) -
                        breakSeconds(session),
                ),
            0,
        );
    }

    function conciseDuration(seconds: number): string {
        const minutes = Math.floor(seconds / 60);
        if (minutes < 1) return t('attendance.duration.less_than_minute');
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        if (hours === 0) return `${minutes} min`;
        if (remainingMinutes === 0) return `${hours} h`;
        return `${hours} h ${remainingMinutes} min`;
    }

    function liveDuration(seconds: number): string {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
    }

    function allBreaks(row: AttendanceRow): BreakRow[] {
        return row.sessions.flatMap((session) => session.breaks);
    }

    function qualityText(row: AttendanceRow): string {
        if (!row.quality.attendance_rating_enabled)
            return t('attendance.quality.disabled');
        if (row.quality.average_score === null)
            return t('attendance.quality.unrated');
        return t(`attendance.quality.${row.quality.band}`, {
            score: row.quality.average_score,
        });
    }

    function qualityClass(row: AttendanceRow): string {
        if (row.quality.band === 'good') return 'text-emerald-700';
        if (row.quality.band === 'warning') return 'text-amber-700';
        if (row.quality.band === 'poor') return 'text-error-red';
        return 'text-on-surface-variant';
    }

    function statusVariant(
        status: AttendanceStatus,
    ): 'success' | 'warning' | 'danger' | 'neutral' {
        if (status === 'present') return 'success';
        if (status === 'break') return 'warning';
        if (status === 'stale') return 'danger';
        return 'neutral';
    }

    function postAction(
        workerId: number,
        action: string,
        confirmed: boolean,
    ): void {
        pendingWorkerId.value = workerId;
        actionForm.worker_id = String(workerId);
        actionForm.action = action;
        actionForm.confirm_without_shift = confirmed;
        actionForm.post(route('attendance.actions.store'), {
            preserveScroll: true,
            onSuccess: () => {
                timerWorkerId.value = String(workerId);
            },
            onFinish: () => {
                pendingWorkerId.value = null;
            },
        });
    }

    async function perform(row: AttendanceRow, action: string): Promise<void> {
        let confirmed = false;
        if (action === 'arrival' && !row.has_current_shift) {
            confirmed = await dialog.confirm({
                title: t('attendance.no_shift.title'),
                message: t('attendance.no_shift.description'),
                confirmLabel: t('attendance.no_shift.confirm'),
                variant: 'warning',
            });
            if (!confirmed) return;
        }
        postAction(row.worker_id, action, confirmed);
    }

    async function performTimerAction(action: string): Promise<void> {
        const workerId = Number(timerWorkerId.value);
        if (!workerId) return;
        if (timerRow.value) {
            await perform(timerRow.value, action);
            return;
        }
        postAction(workerId, action, action === 'arrival');
    }

    let refreshTimer: ReturnType<typeof setInterval> | null = null;

    let clockTimer: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        clockTimer = setInterval(() => {
            nowMs.value = Date.now();
        }, 1000);
        refreshTimer = setInterval(() => {
            if (!actionForm.processing)
                router.reload({
                    only: [
                        'attendance_rows',
                        'off_schedule_workers',
                        'store_state',
                    ],
                });
        }, 30_000);
    });

    onUnmounted(() => {
        if (refreshTimer !== null) clearInterval(refreshTimer);
        if (clockTimer !== null) clearInterval(clockTimer);
    });
    return {
        t,
        route,
        timerWorkerId,
        pendingWorkerId,
        actionForm,
        timerWorkerOptions,
        timerWorkerModel,
        timerRow,
        timerSession,
        timerSeconds,
        timerStatus,
        timerHasShift,
        timeOnly,
        intervalSeconds,
        workedSeconds,
        conciseDuration,
        liveDuration,
        allBreaks,
        qualityText,
        qualityClass,
        statusVariant,
        perform,
        performTimerAction,
    };
}
