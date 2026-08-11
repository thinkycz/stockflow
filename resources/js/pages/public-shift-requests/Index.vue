<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    ClipboardPen,
    LockKeyhole,
    Zap,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ShiftMonthCalendar from '@/components/ShiftMonthCalendar.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { showErrorToast, showSuccessToast } from '@/composables/useClientToast';
import { useRoute } from '@/composables/useRoute';
import { sortShiftsByTime } from '@/lib/shift-calendar';

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

const props = defineProps<{
    store: { name: string };
    workers: Worker[];
    selected_worker_id: number | null;
    shift_requests: ShiftRequest[];
    is_locked: boolean;
    filters: { year: number; month: number };
    share_token: string;
}>();

const { t, locale } = useI18n();
useBoundLocale();
const route = useRoute();

const selectedWorkerId = ref<string>(
    props.selected_worker_id === null ? '' : String(props.selected_worker_id),
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
    const firstOfMonth = new Date(
        props.filters.year,
        props.filters.month - 1,
        1,
    );
    const daysInMonth = new Date(
        props.filters.year,
        props.filters.month,
        0,
    ).getDate();
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

    let startWeekday = firstOfMonth.getDay() - 1;
    if (startWeekday < 0) startWeekday = 6;
    const days: CalendarDay[] = [];

    for (let index = 0; index < startWeekday; index++) {
        const date = new Date(
            props.filters.year,
            props.filters.month - 1,
            -(startWeekday - 1 - index),
        );
        days.push(calendarDay(date, false, requestsByDate));
    }
    for (let day = 1; day <= daysInMonth; day++) {
        days.push(
            calendarDay(
                new Date(props.filters.year, props.filters.month - 1, day),
                true,
                requestsByDate,
            ),
        );
    }
    while (days.length < 42) {
        const previous = days[days.length - 1];
        const date = new Date(
            Number(previous.date.slice(0, 4)),
            Number(previous.date.slice(5, 7)) - 1,
            Number(previous.date.slice(8, 10)) + 1,
        );
        days.push(calendarDay(date, false, requestsByDate));
    }

    return days;
});

function calendarDay(
    date: Date,
    isCurrentMonth: boolean,
    requestsByDate: Map<string, CalendarRequest[]>,
): CalendarDay {
    const dateKey = formatDateKey(date);
    return {
        date: dateKey,
        day: date.getDate(),
        isCurrentMonth,
        shifts: [],
        requests: requestsByDate.get(dateKey) ?? [],
    };
}

function formatDateKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

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
</script>

<template>
    <Head :title="t('shifts.requests.title', { store: store.name })" />

    <main
        class="min-h-screen bg-surface-bg px-4 py-6 font-sans sm:px-6 sm:py-10"
    >
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header class="space-y-3">
                <Link
                    :href="route('public-shifts.index', { token: share_token })"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant transition hover:text-primary"
                >
                    <ChevronLeft :size="13" />
                    {{ t('shifts.requests.back') }}
                </Link>
                <div class="flex items-center gap-3 text-primary">
                    <ClipboardPen :size="24" />
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface sm:text-3xl"
                    >
                        {{ t('shifts.requests.heading') }}
                    </h1>
                </div>
                <p class="text-sm text-on-surface-variant">
                    {{ t('shifts.requests.subtitle', { store: store.name }) }}
                </p>
            </header>

            <Card padded>
                <div
                    class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end"
                >
                    <div class="space-y-2">
                        <Label for="request_worker">{{
                            t('shifts.quick_add.employee')
                        }}</Label>
                        <Select
                            id="request_worker"
                            v-model="selectedWorkerId"
                            :disabled="quickAddActive"
                            :options="workerOptions"
                            :placeholder="t('shifts.select_worker')"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="request_start">{{
                            t('shifts.requests.start_time')
                        }}</Label>
                        <Select
                            id="request_start"
                            v-model="selectedStartTime"
                            :disabled="quickAddActive"
                            :options="timeOptions"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="request_end">{{
                            t('shifts.requests.end_time')
                        }}</Label>
                        <Select
                            id="request_end"
                            v-model="selectedEndTime"
                            :disabled="quickAddActive"
                            :options="timeOptions"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <Button
                            v-if="!quickAddActive"
                            :disabled="
                                is_locked ||
                                selectedWorkerId === '' ||
                                selectedStartTime >= selectedEndTime
                            "
                            @click="startQuickAdd"
                        >
                            <Zap :size="14" />
                            {{ t('shifts.requests.start') }}
                        </Button>
                        <Button
                            v-else
                            variant="secondary"
                            @click="stopQuickAdd"
                        >
                            <Check :size="14" />
                            {{ t('shifts.quick_add.done') }}
                        </Button>
                    </div>
                </div>
                <p
                    v-if="quickAddActive"
                    class="mt-4 text-xs font-semibold text-primary"
                >
                    {{ t('shifts.requests.active_help') }}
                </p>
                <p
                    v-else-if="selectedStartTime >= selectedEndTime"
                    class="mt-4 text-xs font-semibold text-error-red"
                >
                    {{ t('shifts.requests.invalid_time') }}
                </p>
            </Card>

            <div
                v-if="is_locked"
                role="status"
                class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-950"
            >
                <LockKeyhole :size="20" class="mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-bold">
                        {{ t('shifts.requests.locked_title') }}
                    </p>
                    <p class="mt-1 text-xs">
                        {{ t('shifts.requests.locked_help') }}
                    </p>
                </div>
            </div>

            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3 px-1">
                    <div class="flex items-center gap-2">
                        <CalendarDays
                            :size="18"
                            class="text-on-surface-variant"
                        />
                        <h2
                            class="font-heading text-lg font-bold text-on-surface capitalize"
                        >
                            {{ currentMonthLabel }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            type="button"
                            :disabled="isFirstAllowedMonth"
                            :aria-label="t('shifts.prev_month')"
                            @click="navigateMonth(-1)"
                        >
                            <ChevronLeft :size="16" />
                        </Button>
                        <Button
                            variant="ghost"
                            type="button"
                            :aria-label="t('shifts.next_month')"
                            @click="navigateMonth(1)"
                        >
                            <ChevronRight :size="16" />
                        </Button>
                    </div>
                </div>

                <ShiftMonthCalendar
                    :days="calendarDays"
                    :weekday-labels="weekdayLabels"
                    :interactive="quickAddActive && !is_locked"
                    :editable="!is_locked"
                    :quick-add-active="quickAddActive"
                    :pending-dates="pendingDates"
                    @activate="toggleRequest"
                />
            </section>
        </div>
    </main>
</template>
