<script setup lang="ts">
import {
    CalendarPlus,
    CircleOff,
    ClipboardList,
    Clock3,
    LoaderCircle,
    UsersRound,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type CalendarShift = {
    id: number;
    worker_name: string;
    worker_color: string;
    start_time: string;
    end_time: string;
    attendance_rating?: {
        state: 'future' | 'pending' | 'scored' | 'disabled';
        score: number | null;
        band: 'good' | 'warning' | 'poor' | null;
    };
};

type CalendarRequest = {
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

const props = withDefaults(
    defineProps<{
        days: CalendarDay[];
        weekdayLabels: string[];
        interactive?: boolean;
        editable?: boolean;
        quickAddActive?: boolean;
        mobileMonthOnly?: boolean;
        pendingDates?: ReadonlySet<string>;
    }>(),
    {
        interactive: false,
        editable: false,
        quickAddActive: false,
        mobileMonthOnly: false,
        pendingDates: () => new Set<string>(),
    },
);

const emit = defineEmits<{
    activate: [day: CalendarDay];
}>();

const { t, locale } = useI18n();
const selectedDate = ref<string>('');
const mobileView = ref<'day' | 'month'>(
    props.mobileMonthOnly ? 'month' : 'day',
);

const dateLocale = computed<string>(() => {
    if (locale.value === 'en') return 'en-US';
    if (locale.value === 'sk') return 'sk-SK';
    return 'cs-CZ';
});

const todayKey = formatDateKey(new Date());
const currentMonthDays = computed<CalendarDay[]>(() =>
    props.days.filter((day) => day.isCurrentMonth),
);
const selectedDay = computed<CalendarDay | undefined>(() =>
    currentMonthDays.value.find((day) => day.date === selectedDate.value),
);
const selectedDayLabel = computed<string>(() => {
    if (selectedDay.value === undefined) return '';

    return new Intl.DateTimeFormat(dateLocale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(parseDateKey(selectedDay.value.date));
});

watch(
    () => props.days.map((day) => day.date).join(','),
    () => {
        const today = currentMonthDays.value.find(
            (day) => day.date === todayKey,
        );
        const firstScheduled = currentMonthDays.value.find(
            (day) => day.shifts.length > 0 || day.requests.length > 0,
        );
        selectedDate.value =
            today?.date ??
            firstScheduled?.date ??
            currentMonthDays.value[0]?.date ??
            '';
    },
    { immediate: true },
);

watch(
    () => props.quickAddActive,
    (active) => {
        if (active && !props.mobileMonthOnly) mobileView.value = 'day';
    },
);

function selectMobileDay(day: CalendarDay): void {
    if (!day.isCurrentMonth) return;

    selectedDate.value = day.date;
    if (props.quickAddActive && props.editable) {
        emit('activate', day);
    }
}

function ratingLabel(shift: CalendarShift): string {
    const rating = shift.attendance_rating;
    if (rating === undefined || rating.state === 'future') {
        return t('shifts.rating.state.future');
    }
    if (rating.state === 'pending') {
        return t('shifts.rating.state.pending');
    }
    if (rating.state === 'disabled') {
        return t('shifts.rating.state.disabled');
    }

    return t('shifts.rating.score_label', { score: rating.score });
}

function ratingClass(shift: CalendarShift): string {
    const rating = shift.attendance_rating;
    if (rating?.band === 'good') return 'bg-emerald-100 text-emerald-800';
    if (rating?.band === 'warning') return 'bg-amber-100 text-amber-800';
    if (rating?.band === 'poor') return 'bg-rose-100 text-rose-800';

    return 'bg-surface-container-high text-on-surface-variant';
}

function activateDay(day: CalendarDay): void {
    if (props.interactive && day.isCurrentMonth) {
        emit('activate', day);
    }
}

function openDayFromMonth(day: CalendarDay): void {
    if (!day.isCurrentMonth) return;

    if (props.mobileMonthOnly) {
        activateDay(day);
        return;
    }

    selectedDate.value = day.date;
    mobileView.value = 'day';
}

function parseDateKey(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function formatDateKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
</script>

<template>
    <div>
        <div
            class="hidden overflow-hidden rounded-2xl border border-outline-glass bg-outline-glass shadow-[0_12px_36px_rgba(15,23,42,0.06)] md:grid md:grid-cols-7 md:gap-px"
        >
            <div
                v-for="label in weekdayLabels"
                :key="label"
                class="bg-surface-container-low px-2 py-3 text-center text-[11px] font-bold tracking-[0.12em] text-on-surface-variant uppercase"
            >
                {{ label }}
            </div>

            <button
                v-for="day in days"
                :key="day.date"
                type="button"
                :disabled="!interactive || !day.isCurrentMonth"
                :class="[
                    'group flex min-h-[118px] flex-col items-stretch justify-start overflow-hidden bg-surface-container-lowest p-2.5 text-left transition lg:min-h-[138px] lg:p-3',
                    !day.isCurrentMonth
                        ? 'bg-surface-container-low/70 text-on-surface-variant'
                        : '',
                    interactive && day.isCurrentMonth
                        ? 'cursor-pointer hover:relative hover:z-10 hover:bg-primary-fixed hover:shadow-[inset_0_0_0_1px_rgba(15,23,42,0.2)]'
                        : 'cursor-default',
                    quickAddActive && day.isCurrentMonth
                        ? 'hover:bg-emerald-50'
                        : '',
                ]"
                :data-testid="`calendar-day-${day.date}`"
                @click="activateDay(day)"
            >
                <div
                    class="mb-2 flex h-7 shrink-0 items-center justify-between"
                >
                    <span
                        :class="[
                            'flex size-7 items-center justify-center rounded-full text-xs font-bold',
                            day.date === todayKey
                                ? 'bg-primary text-white shadow-sm'
                                : day.isCurrentMonth
                                  ? 'text-on-surface'
                                  : 'text-on-surface-variant/60',
                        ]"
                    >
                        {{ day.day }}
                    </span>
                    <LoaderCircle
                        v-if="pendingDates.has(day.date)"
                        :size="14"
                        class="animate-spin text-primary"
                    />
                    <CalendarPlus
                        v-else-if="editable && day.isCurrentMonth"
                        :size="14"
                        class="text-on-surface-variant/0 transition group-hover:text-primary/60"
                    />
                </div>

                <div class="space-y-1.5">
                    <div
                        v-for="shift in day.shifts"
                        :key="shift.id"
                        data-testid="calendar-shift"
                        class="rounded-lg border border-outline-glass bg-white px-2 py-1.5 shadow-[0_1px_2px_rgba(15,23,42,0.04)]"
                        :style="{
                            borderLeft: `3px solid ${shift.worker_color}`,
                        }"
                    >
                        <div
                            class="truncate text-[11px] font-bold text-on-surface"
                        >
                            {{ shift.start_time }}–{{ shift.end_time }}
                        </div>
                        <div
                            class="mt-0.5 flex items-center justify-between gap-1 text-[10px] text-on-surface-variant"
                        >
                            <span class="truncate">{{
                                shift.worker_name
                            }}</span>
                            <span
                                class="shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-bold"
                                :class="ratingClass(shift)"
                                :aria-label="ratingLabel(shift)"
                            >
                                <CircleOff
                                    v-if="
                                        shift.attendance_rating?.state ===
                                        'disabled'
                                    "
                                    :size="12"
                                    aria-hidden="true"
                                />
                                <template v-else>
                                    {{
                                        shift.attendance_rating?.score ??
                                        (shift.attendance_rating?.state ===
                                        'pending'
                                            ? '…'
                                            : '—')
                                    }}
                                </template>
                            </span>
                        </div>
                    </div>
                    <div
                        v-for="shiftRequest in day.requests"
                        :key="`request-${shiftRequest.id}`"
                        data-testid="calendar-shift-request"
                        class="rounded-lg border border-dashed border-primary/45 bg-primary-fixed/45 px-2 py-1.5"
                        :style="{
                            borderLeft: `3px dashed ${shiftRequest.worker_color}`,
                        }"
                    >
                        <div
                            class="truncate text-[11px] font-bold text-on-surface"
                        >
                            {{ shiftRequest.start_time }}–{{
                                shiftRequest.end_time
                            }}
                        </div>
                        <div
                            class="mt-0.5 flex items-center justify-between gap-1 text-[10px] text-on-surface-variant"
                        >
                            <span class="truncate">{{
                                shiftRequest.worker_name
                            }}</span>
                            <span class="shrink-0 font-bold uppercase">{{
                                t('shifts.requests.item_label')
                            }}</span>
                        </div>
                    </div>
                </div>
            </button>
        </div>

        <div class="space-y-4 md:hidden">
            <div
                v-if="!mobileMonthOnly"
                class="grid grid-cols-2 gap-1 rounded-xl bg-surface-container-low p-1"
                :aria-label="t('shifts.mobile.view_label')"
            >
                <button
                    type="button"
                    :aria-pressed="mobileView === 'day'"
                    :class="[
                        'h-9 rounded-lg px-3 text-xs font-bold transition',
                        mobileView === 'day'
                            ? 'bg-white text-on-surface shadow-sm'
                            : 'text-on-surface-variant hover:text-on-surface',
                    ]"
                    @click="mobileView = 'day'"
                >
                    {{ t('shifts.mobile.day_view') }}
                </button>
                <button
                    type="button"
                    :disabled="quickAddActive"
                    :aria-pressed="mobileView === 'month'"
                    :class="[
                        'h-9 rounded-lg px-3 text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-40',
                        mobileView === 'month'
                            ? 'bg-white text-on-surface shadow-sm'
                            : 'text-on-surface-variant hover:text-on-surface',
                    ]"
                    @click="mobileView = 'month'"
                >
                    {{ t('shifts.mobile.month_view') }}
                </button>
            </div>

            <div
                v-if="!mobileMonthOnly && mobileView === 'day'"
                data-testid="mobile-day-view"
                class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest p-2 shadow-sm"
            >
                <div class="grid grid-cols-7">
                    <div
                        v-for="label in weekdayLabels"
                        :key="label"
                        class="py-2 text-center text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
                    >
                        {{ label }}
                    </div>
                    <button
                        v-for="day in days"
                        :key="day.date"
                        type="button"
                        :disabled="!day.isCurrentMonth"
                        :aria-pressed="day.date === selectedDate"
                        :class="[
                            'relative grid min-h-11 grid-rows-[1rem_0.25rem] place-items-center content-center gap-1 rounded-xl text-xs font-semibold transition',
                            !day.isCurrentMonth
                                ? 'text-on-surface-variant/35'
                                : 'text-on-surface hover:bg-surface-container-low',
                            day.date === selectedDate
                                ? 'bg-primary text-white shadow-md hover:bg-primary'
                                : '',
                            day.date === todayKey && day.date !== selectedDate
                                ? 'ring-1 ring-primary/40'
                                : '',
                        ]"
                        :data-testid="`mobile-calendar-day-${day.date}`"
                        @click="selectMobileDay(day)"
                    >
                        <span>{{ day.day }}</span>
                        <span class="flex h-1 gap-0.5" aria-hidden="true">
                            <span
                                v-for="shift in day.shifts.slice(0, 3)"
                                :key="shift.id"
                                class="size-1 rounded-full"
                                :class="
                                    day.date === selectedDate
                                        ? 'bg-white'
                                        : 'bg-primary'
                                "
                            />
                            <span
                                v-for="shiftRequest in day.requests.slice(0, 3)"
                                :key="`request-${shiftRequest.id}`"
                                class="size-1 rounded-full border border-current bg-transparent"
                            />
                        </span>
                        <LoaderCircle
                            v-if="pendingDates.has(day.date)"
                            :size="12"
                            class="absolute top-1 right-1 animate-spin"
                        />
                    </button>
                </div>
            </div>

            <section
                v-if="!mobileMonthOnly && mobileView === 'day' && selectedDay"
                class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-outline-glass bg-surface-container-low px-4 py-3.5"
                >
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-[0.14em] text-on-surface-variant uppercase"
                        >
                            {{ t('shifts.mobile.selected_day') }}
                        </p>
                        <h3
                            class="mt-0.5 font-heading text-base font-bold text-on-surface capitalize"
                        >
                            {{ selectedDayLabel }}
                        </h3>
                    </div>
                    <button
                        v-if="editable && !quickAddActive"
                        type="button"
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-md transition active:scale-95"
                        :aria-label="t('shifts.add_shift')"
                        @click="activateDay(selectedDay)"
                    >
                        <CalendarPlus :size="18" />
                    </button>
                </div>

                <div
                    v-if="
                        selectedDay.shifts.length > 0 ||
                        selectedDay.requests.length > 0
                    "
                    class="divide-y divide-outline-glass"
                >
                    <button
                        v-for="shift in selectedDay.shifts"
                        :key="shift.id"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3.5 text-left hover:bg-surface-container-low"
                        @click="activateDay(selectedDay)"
                    >
                        <span
                            class="h-10 w-1 shrink-0 rounded-full"
                            :style="{ backgroundColor: shift.worker_color }"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-bold text-on-surface"
                            >
                                {{ shift.worker_name }}
                            </p>
                            <p
                                class="mt-1 flex items-center gap-1.5 text-xs font-medium text-on-surface-variant"
                            >
                                <Clock3 :size="13" />
                                {{ shift.start_time }}–{{ shift.end_time }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-1 text-xs font-bold"
                            :class="ratingClass(shift)"
                            :aria-label="ratingLabel(shift)"
                        >
                            <CircleOff
                                v-if="
                                    shift.attendance_rating?.state ===
                                    'disabled'
                                "
                                :size="14"
                                aria-hidden="true"
                            />
                            <template v-else>
                                {{
                                    shift.attendance_rating?.score ??
                                    (shift.attendance_rating?.state ===
                                    'pending'
                                        ? '…'
                                        : '—')
                                }}
                            </template>
                        </span>
                    </button>
                    <div
                        v-for="shiftRequest in selectedDay.requests"
                        :key="`request-${shiftRequest.id}`"
                        data-testid="mobile-calendar-shift-request"
                        class="flex w-full items-center gap-3 bg-primary-fixed/35 px-4 py-3.5 text-left"
                    >
                        <span
                            class="h-10 w-1 shrink-0 rounded-full border-l-2 border-dashed"
                            :style="{ borderColor: shiftRequest.worker_color }"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-bold text-on-surface"
                            >
                                {{ shiftRequest.worker_name }}
                            </p>
                            <p
                                class="mt-1 flex items-center gap-1.5 text-xs font-medium text-on-surface-variant"
                            >
                                <Clock3 :size="13" />
                                {{ shiftRequest.start_time }}–{{
                                    shiftRequest.end_time
                                }}
                            </p>
                        </div>
                        <span
                            class="flex items-center gap-1 text-[10px] font-bold text-primary uppercase"
                        >
                            <ClipboardList :size="13" />
                            {{ t('shifts.requests.item_label') }}
                        </span>
                    </div>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center px-5 py-8 text-center"
                >
                    <span
                        class="mb-3 flex size-11 items-center justify-center rounded-full bg-surface-container-low text-on-surface-variant"
                    >
                        <UsersRound :size="20" />
                    </span>
                    <p class="text-sm font-semibold text-on-surface">
                        {{ t('shifts.mobile.no_shifts') }}
                    </p>
                    <button
                        v-if="editable && !quickAddActive"
                        type="button"
                        class="mt-3 text-xs font-bold text-primary underline-offset-4 hover:underline"
                        @click="activateDay(selectedDay)"
                    >
                        {{ t('shifts.add_shift') }}
                    </button>
                </div>
            </section>

            <section
                v-if="mobileMonthOnly || mobileView === 'month'"
                data-testid="mobile-month-view"
                class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
            >
                <div
                    v-if="!mobileMonthOnly"
                    class="border-b border-outline-glass bg-surface-container-low px-4 py-3.5"
                >
                    <p
                        class="text-[10px] font-bold tracking-[0.14em] text-on-surface-variant uppercase"
                    >
                        {{ t('shifts.mobile.month_view') }}
                    </p>
                    <h3
                        class="mt-0.5 font-heading text-base font-bold text-on-surface"
                    >
                        {{ t('shifts.mobile.full_calendar') }}
                    </h3>
                </div>

                <div
                    data-testid="mobile-month-scroller"
                    class="overflow-x-auto overscroll-x-contain"
                >
                    <div
                        class="grid min-w-[720px] grid-cols-7 gap-px bg-outline-glass"
                    >
                        <div
                            v-for="label in weekdayLabels"
                            :key="label"
                            class="bg-surface-container-low px-2 py-2.5 text-center text-[10px] font-bold tracking-[0.12em] text-on-surface-variant uppercase"
                        >
                            {{ label }}
                        </div>
                        <button
                            v-for="day in days"
                            :key="day.date"
                            type="button"
                            :disabled="!day.isCurrentMonth"
                            :class="[
                                'flex min-h-[104px] flex-col items-stretch justify-start bg-white p-2 text-left',
                                day.isCurrentMonth
                                    ? 'cursor-pointer hover:bg-primary-fixed'
                                    : 'cursor-default bg-surface-container-low/70 text-on-surface-variant/50',
                            ]"
                            :data-testid="`mobile-month-calendar-day-${day.date}`"
                            @click="openDayFromMonth(day)"
                        >
                            <span
                                :class="[
                                    'mb-1.5 flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                    day.date === todayKey
                                        ? 'bg-primary text-white'
                                        : '',
                                ]"
                            >
                                {{ day.day }}
                            </span>
                            <div class="space-y-1">
                                <div
                                    v-for="shift in day.shifts"
                                    :key="shift.id"
                                    data-testid="mobile-month-calendar-shift"
                                    class="rounded-md border border-outline-glass bg-white px-1.5 py-1 shadow-sm"
                                    :style="{
                                        borderLeft: `3px solid ${shift.worker_color}`,
                                    }"
                                >
                                    <div
                                        class="truncate text-[10px] font-bold text-on-surface"
                                    >
                                        {{ shift.start_time }}–{{
                                            shift.end_time
                                        }}
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center justify-between gap-1 text-[9px] text-on-surface-variant"
                                    >
                                        <span class="truncate">{{
                                            shift.worker_name
                                        }}</span>
                                        <span
                                            class="shrink-0 rounded-full px-1 py-0.5 text-[8px] font-bold"
                                            :class="ratingClass(shift)"
                                            :aria-label="ratingLabel(shift)"
                                        >
                                            <CircleOff
                                                v-if="
                                                    shift.attendance_rating
                                                        ?.state === 'disabled'
                                                "
                                                :size="10"
                                                aria-hidden="true"
                                            />
                                            <template v-else>
                                                {{
                                                    shift.attendance_rating
                                                        ?.score ?? '—'
                                                }}
                                            </template>
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-for="shiftRequest in day.requests"
                                    :key="`request-${shiftRequest.id}`"
                                    data-testid="mobile-month-calendar-shift-request"
                                    class="rounded-md border border-dashed border-primary/45 bg-primary-fixed/45 px-1.5 py-1"
                                    :style="{
                                        borderLeft: `3px dashed ${shiftRequest.worker_color}`,
                                    }"
                                >
                                    <div
                                        class="truncate text-[10px] font-bold text-on-surface"
                                    >
                                        {{ shiftRequest.start_time }}–{{
                                            shiftRequest.end_time
                                        }}
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center justify-between gap-1 text-[9px] text-on-surface-variant"
                                    >
                                        <span class="truncate">{{
                                            shiftRequest.worker_name
                                        }}</span>
                                        <span class="font-bold uppercase">{{
                                            t('shifts.requests.item_label')
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
