import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { showErrorToast, showSuccessToast } from '@/composables/useClientToast';
import { useRoute } from '@/composables/useRoute';
import {
    calendarMonthDays,
    sortShiftsByTime,
} from '@/features/shifts/shift-calendar';

type Worker = {
    id: number;
    name: string;
    color: string;
};

type ShiftRequest = {
    id: number;
    worker_id: number;
    date: string;
    start_time: string;
    end_time: string;
};

type CalendarRequest = ShiftRequest & {
    worker_name: string;
    worker_color: string;
};

type CalendarShift = {
    id: number;
    worker_name: string;
    worker_color: string;
    start_time: string;
    end_time: string;
};

type CalendarDay = {
    date: string;
    day: number;
    isCurrentMonth: boolean;
    shifts: CalendarShift[];
    requests: CalendarRequest[];
};
export type PublicShiftRequestsProps = {
    store: { name: string };
    workers: Worker[];
    selected_worker_id: number | null;
    shift_requests: ShiftRequest[];
    is_locked: boolean;
    filters: { year: number; month: number };
    share_token: string;
};

export function usePublicShiftRequests(props: PublicShiftRequestsProps) {
    const { t, locale } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const selectedWorkerId = ref<string>(
        props.selected_worker_id === null
            ? ''
            : String(props.selected_worker_id),
    );

    const selectedStartTime = ref<string>('09:00');

    const selectedEndTime = ref<string>('17:00');

    const quickAddActive = ref<boolean>(false);

    const pendingDates = ref<Set<string>>(new Set());

    const localRequests = ref<ShiftRequest[]>([...props.shift_requests]);

    watch(
        () => props.shift_requests,
        (requests) => {
            localRequests.value = [...requests];
        },
    );

    watch(selectedWorkerId, (workerId) => {
        quickAddActive.value = false;
        router.get(
            route('public-shift-requests.index', { token: props.share_token }),
            {
                year: props.filters.year,
                month: props.filters.month,
                worker_id: workerId === '' ? undefined : Number(workerId),
            },
            { preserveState: true, preserveScroll: true },
        );
    });

    const dateLocale = computed<string>(() => {
        if (locale.value === 'en') return 'en-US';
        if (locale.value === 'sk') return 'sk-SK';
        return 'cs-CZ';
    });

    const currentMonthLabel = computed<string>(() =>
        new Intl.DateTimeFormat(dateLocale.value, {
            month: 'long',
            year: 'numeric',
        }).format(new Date(props.filters.year, props.filters.month - 1, 1)),
    );

    const weekdayLabels = computed<string[]>(() => {
        const formatter = new Intl.DateTimeFormat(dateLocale.value, {
            weekday: 'short',
        });

        return Array.from({ length: 7 }, (_, index) =>
            formatter.format(new Date(2026, 0, 5 + index)),
        );
    });

    const workerOptions = computed(() =>
        props.workers.map((worker) => ({
            value: String(worker.id),
            label: worker.name,
        })),
    );

    const timeOptions = Array.from({ length: 96 }, (_, index) => {
        const hour = Math.floor(index / 4);
        const minute = (index % 4) * 15;
        const value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        return { value, label: value };
    });

    const selectedWorker = computed<Worker | undefined>(() =>
        props.workers.find(
            (worker) => String(worker.id) === selectedWorkerId.value,
        ),
    );

    const nextMonth = new Date();

    nextMonth.setDate(1);

    nextMonth.setMonth(nextMonth.getMonth() + 1);

    const isFirstAllowedMonth = computed<boolean>(
        () =>
            props.filters.year === nextMonth.getFullYear() &&
            props.filters.month === nextMonth.getMonth() + 1,
    );

    const calendarDays = computed<CalendarDay[]>(() => {
        const requestsByDate = new Map<string, CalendarRequest[]>();

        if (selectedWorker.value !== undefined) {
            for (const shiftRequest of localRequests.value) {
                const requests = requestsByDate.get(shiftRequest.date) ?? [];
                requests.push({
                    ...shiftRequest,
                    worker_name: selectedWorker.value.name,
                    worker_color: selectedWorker.value.color,
                });
                requestsByDate.set(shiftRequest.date, requests);
            }
        }

        for (const [date, requests] of requestsByDate) {
            requestsByDate.set(date, sortShiftsByTime(requests));
        }

        return calendarMonthDays(props.filters.year, props.filters.month).map(
            (day) => ({
                ...day,
                shifts: [],
                requests: requestsByDate.get(day.date) ?? [],
            }),
        );
    });

    function navigateMonth(delta: number): void {
        if (delta < 0 && isFirstAllowedMonth.value) return;
        const date = new Date(
            props.filters.year,
            props.filters.month - 1 + delta,
            1,
        );
        router.get(
            route('public-shift-requests.index', { token: props.share_token }),
            {
                year: date.getFullYear(),
                month: date.getMonth() + 1,
                worker_id:
                    selectedWorkerId.value === ''
                        ? undefined
                        : Number(selectedWorkerId.value),
            },
            { preserveScroll: true },
        );
    }

    function startQuickAdd(): void {
        if (
            props.is_locked ||
            selectedWorkerId.value === '' ||
            selectedStartTime.value >= selectedEndTime.value
        ) {
            return;
        }
        quickAddActive.value = true;
    }

    function stopQuickAdd(): void {
        quickAddActive.value = false;
        pendingDates.value = new Set();
    }

    async function toggleRequest(
        day: Pick<CalendarDay, 'date' | 'isCurrentMonth'>,
    ): Promise<void> {
        if (
            !quickAddActive.value ||
            !day.isCurrentMonth ||
            pendingDates.value.has(day.date) ||
            selectedWorkerId.value === ''
        ) {
            return;
        }

        pendingDates.value = new Set(pendingDates.value).add(day.date);
        try {
            const response = await window.axios.post<{
                status: 'created' | 'updated' | 'deleted';
                request: ShiftRequest | null;
            }>(
                route('public-shift-requests.toggle', {
                    token: props.share_token,
                }),
                {
                    worker_id: Number(selectedWorkerId.value),
                    date: day.date,
                    start_time: selectedStartTime.value,
                    end_time: selectedEndTime.value,
                },
            );

            localRequests.value = localRequests.value.filter(
                (shiftRequest) => shiftRequest.date !== day.date,
            );
            if (response.data.request !== null) {
                localRequests.value = [
                    ...localRequests.value,
                    response.data.request,
                ];
            }
            showSuccessToast(t(`shifts.requests.${response.data.status}`));
        } catch {
            showErrorToast(t('shifts.requests.failed'));
        } finally {
            const pending = new Set(pendingDates.value);
            pending.delete(day.date);
            pendingDates.value = pending;
        }
    }
    return {
        t,
        route,
        selectedWorkerId,
        selectedStartTime,
        selectedEndTime,
        quickAddActive,
        pendingDates,
        currentMonthLabel,
        weekdayLabels,
        workerOptions,
        timeOptions,
        isFirstAllowedMonth,
        calendarDays,
        navigateMonth,
        startQuickAdd,
        stopQuickAdd,
        toggleRequest,
    };
}
