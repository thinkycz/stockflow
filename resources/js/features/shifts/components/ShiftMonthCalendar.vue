<script lang="ts">
const mobileViewsByPage = new Map<string, 'compact' | 'full'>();
</script>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CalendarPlus, CircleOff, Hand, LoaderCircle } from '@lucide/vue';
import { ref, watch } from 'vue';
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
        pendingDates?: ReadonlySet<string>;
    }>(),
    {
        interactive: false,
        editable: false,
        quickAddActive: false,
        pendingDates: () => new Set<string>(),
    },
);

const emit = defineEmits<{
    activate: [day: CalendarDay];
}>();

const { t } = useI18n();
const pageKey = usePage().component;
const mobileView = ref<'compact' | 'full'>(
    mobileViewsByPage.get(pageKey) ?? 'compact',
);

watch(mobileView, (view) => mobileViewsByPage.set(pageKey, view));

const todayKey = formatDateKey(new Date());

type CompactEntry = {
    key: string;
    kind: 'shift' | 'request';
    worker_name: string;
    worker_color: string;
    start_time: string;
    end_time: string;
};

function compactEntries(day: CalendarDay): CompactEntry[] {
    return [
        ...day.shifts.map((shift) => ({
            ...shift,
            key: `shift-${shift.id}`,
            kind: 'shift' as const,
        })),
        ...day.requests.map((request) => ({
            ...request,
            key: `request-${request.id}`,
            kind: 'request' as const,
        })),
    ];
}

function compactEntryLabel(entry: CompactEntry): string {
    return t(
        entry.kind === 'shift'
            ? 'shifts.mobile.compact_shift_label'
            : 'shifts.mobile.compact_request_label',
        {
            name: entry.worker_name,
            start: entry.start_time,
            end: entry.end_time,
        },
    );
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
                            <span
                                class="shrink-0 text-primary"
                                :aria-label="t('shifts.requests.item_label')"
                                :title="t('shifts.requests.item_label')"
                            >
                                <Hand :size="12" aria-hidden="true" />
                            </span>
                        </div>
                    </div>
                </div>
            </button>
        </div>

        <div class="space-y-4 md:hidden">
            <div
                class="grid grid-cols-2 gap-1 rounded-xl bg-surface-container-low p-1"
                :aria-label="t('shifts.mobile.view_label')"
            >
                <button
                    type="button"
                    :aria-pressed="mobileView === 'compact'"
                    :class="[
                        'h-9 rounded-lg px-3 text-xs font-bold transition',
                        mobileView === 'compact'
                            ? 'bg-white text-on-surface shadow-sm'
                            : 'text-on-surface-variant hover:text-on-surface',
                    ]"
                    @click="mobileView = 'compact'"
                >
                    {{ t('shifts.mobile.compact_view') }}
                </button>
                <button
                    type="button"
                    :aria-pressed="mobileView === 'full'"
                    :class="[
                        'h-9 rounded-lg px-3 text-xs font-bold transition',
                        mobileView === 'full'
                            ? 'bg-white text-on-surface shadow-sm'
                            : 'text-on-surface-variant hover:text-on-surface',
                    ]"
                    @click="mobileView = 'full'"
                >
                    {{ t('shifts.mobile.full_view') }}
                </button>
            </div>

            <section
                v-if="mobileView === 'compact'"
                data-testid="mobile-compact-view"
                class="overflow-hidden rounded-2xl border border-outline-glass bg-outline-glass shadow-sm"
            >
                <div class="grid w-full grid-cols-7 gap-px">
                    <div
                        v-for="label in weekdayLabels"
                        :key="label"
                        class="min-w-0 bg-surface-container-low py-2 text-center text-[9px] font-bold tracking-wide text-on-surface-variant uppercase"
                    >
                        {{ label }}
                    </div>
                    <button
                        v-for="day in days"
                        :key="day.date"
                        type="button"
                        :disabled="!interactive || !day.isCurrentMonth"
                        :class="[
                            'relative flex min-h-[76px] min-w-0 flex-col items-center bg-white px-0.5 py-1.5 text-xs font-semibold transition',
                            day.isCurrentMonth
                                ? interactive
                                    ? 'cursor-pointer hover:bg-primary-fixed'
                                    : 'cursor-default'
                                : 'cursor-default bg-surface-container-low/70 text-on-surface-variant/35',
                        ]"
                        :data-testid="`mobile-compact-calendar-day-${day.date}`"
                        @click="activateDay(day)"
                    >
                        <span
                            :class="[
                                'mb-1 flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                day.date === todayKey
                                    ? 'bg-primary text-white'
                                    : '',
                            ]"
                        >
                            {{ day.day }}
                        </span>
                        <span class="flex w-full min-w-0 flex-col gap-0.5">
                            <span
                                v-for="entry in compactEntries(day).slice(0, 3)"
                                :key="entry.key"
                                data-testid="mobile-compact-calendar-entry"
                                :aria-label="compactEntryLabel(entry)"
                                :title="compactEntryLabel(entry)"
                                :class="[
                                    'block min-h-8 min-w-0 rounded px-0.5 py-0.5 text-center text-[8px] leading-tight font-bold',
                                    entry.kind === 'request'
                                        ? 'border border-dashed bg-white'
                                        : 'text-white',
                                ]"
                                :style="
                                    entry.kind === 'request'
                                        ? {
                                              borderColor: entry.worker_color,
                                              color: entry.worker_color,
                                          }
                                        : {
                                              backgroundColor:
                                                  entry.worker_color,
                                          }
                                "
                            >
                                <span
                                    aria-hidden="true"
                                    class="flex min-w-0 flex-col"
                                >
                                    <span class="whitespace-nowrap">{{
                                        entry.start_time
                                    }}</span>
                                    <span class="block min-w-0 truncate">{{
                                        entry.worker_name
                                    }}</span>
                                </span>
                            </span>
                            <span
                                v-if="compactEntries(day).length > 3"
                                data-testid="mobile-compact-calendar-overflow"
                                class="block h-4 text-center text-[9px] leading-4 font-bold text-on-surface-variant"
                                :aria-label="
                                    t('shifts.mobile.more_entries', {
                                        count: compactEntries(day).length - 3,
                                    })
                                "
                            >
                                +{{ compactEntries(day).length - 3 }}
                            </span>
                        </span>
                        <LoaderCircle
                            v-if="pendingDates.has(day.date)"
                            :size="12"
                            class="absolute top-1 right-1 animate-spin text-primary"
                        />
                    </button>
                </div>
            </section>

            <section
                v-if="mobileView === 'full'"
                data-testid="mobile-full-view"
                class="overflow-hidden rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm"
            >
                <div
                    data-testid="mobile-full-scroller"
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
                            :disabled="!interactive || !day.isCurrentMonth"
                            :class="[
                                'flex min-h-[104px] flex-col items-stretch justify-start bg-white p-2 text-left',
                                day.isCurrentMonth
                                    ? interactive
                                        ? 'cursor-pointer hover:bg-primary-fixed'
                                        : 'cursor-default'
                                    : 'cursor-default bg-surface-container-low/70 text-on-surface-variant/50',
                            ]"
                            :data-testid="`mobile-full-calendar-day-${day.date}`"
                            @click="activateDay(day)"
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
                                    data-testid="mobile-full-calendar-shift"
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
                                    data-testid="mobile-full-calendar-shift-request"
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
                                        <span
                                            class="shrink-0 text-primary"
                                            :aria-label="
                                                t('shifts.requests.item_label')
                                            "
                                            :title="
                                                t('shifts.requests.item_label')
                                            "
                                        >
                                            <Hand
                                                :size="11"
                                                aria-hidden="true"
                                            />
                                        </span>
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
