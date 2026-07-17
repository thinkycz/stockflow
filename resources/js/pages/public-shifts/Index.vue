<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type Shift = {
    id: number;
    worker_name: string;
    worker_color: string;
    date: string;
    start_time: string;
    end_time: string;
};

type CalendarDay = {
    date: string;
    day: number;
    isCurrentMonth: boolean;
    shifts: Shift[];
};

const props = defineProps<{
    store: { name: string };
    shifts: Shift[];
    filters: {
        year: number;
        month: number;
    };
    share_token: string;
}>();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

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
    const shiftsByDate = new Map<string, Shift[]>();

    for (const shift of props.shifts) {
        const shifts = shiftsByDate.get(shift.date) ?? [];
        shifts.push(shift);
        shiftsByDate.set(shift.date, shifts);
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
        const dateKey = formatDateKey(date);
        days.push({
            date: dateKey,
            day: date.getDate(),
            isCurrentMonth: false,
            shifts: shiftsByDate.get(dateKey) ?? [],
        });
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(props.filters.year, props.filters.month - 1, day);
        const dateKey = formatDateKey(date);
        days.push({
            date: dateKey,
            day,
            isCurrentMonth: true,
            shifts: shiftsByDate.get(dateKey) ?? [],
        });
    }

    while (days.length < 42) {
        const previous = days[days.length - 1];
        const date = new Date(
            Number(previous.date.slice(0, 4)),
            Number(previous.date.slice(5, 7)) - 1,
            Number(previous.date.slice(8, 10)) + 1,
        );
        const dateKey = formatDateKey(date);
        days.push({
            date: dateKey,
            day: date.getDate(),
            isCurrentMonth: false,
            shifts: shiftsByDate.get(dateKey) ?? [],
        });
    }

    return days;
});

function formatDateKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function navigateMonth(delta: number): void {
    const date = new Date(
        props.filters.year,
        props.filters.month - 1 + delta,
        1,
    );

    router.get(
        route('public-shifts.index', { token: props.share_token }),
        { year: date.getFullYear(), month: date.getMonth() + 1 },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('shifts.public_title', { store: store.name })" />

    <main
        class="min-h-screen bg-surface-bg px-4 py-6 font-sans sm:px-6 sm:py-10"
    >
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header class="flex flex-col gap-2">
                <div class="flex items-center gap-3 text-primary">
                    <CalendarDays :size="24" />
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface sm:text-3xl"
                    >
                        {{ store.name }}
                    </h1>
                </div>
                <p class="text-sm text-on-surface-variant">
                    {{ t('shifts.public_subtitle') }}
                </p>
            </header>

            <Card padded>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2
                        class="font-heading text-lg font-bold capitalize text-on-surface sm:text-xl"
                    >
                        {{ currentMonthLabel }}
                    </h2>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            type="button"
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

                <div class="overflow-x-auto">
                    <div class="grid min-w-[720px] grid-cols-7 gap-px">
                        <div
                            v-for="label in weekdayLabels"
                            :key="label"
                            class="bg-surface-container-low py-2 text-center text-xs font-semibold capitalize text-on-surface-variant"
                        >
                            {{ label }}
                        </div>
                        <div
                            v-for="day in calendarDays"
                            :key="day.date"
                            :class="[
                                'min-h-[110px] border border-outline-glass p-2',
                                day.isCurrentMonth
                                    ? 'bg-surface-container-lowest'
                                    : 'bg-surface-container-high opacity-50',
                            ]"
                        >
                            <div
                                class="mb-1.5 text-xs font-semibold"
                                :class="
                                    day.isCurrentMonth
                                        ? 'text-on-surface'
                                        : 'text-on-surface-variant'
                                "
                            >
                                {{ day.day }}
                            </div>
                            <div class="space-y-1">
                                <div
                                    v-for="shift in day.shifts"
                                    :key="shift.id"
                                    class="rounded-md border px-2 py-1.5 text-xs leading-tight"
                                    :style="{
                                        backgroundColor: `${shift.worker_color}18`,
                                        borderColor: `${shift.worker_color}40`,
                                        color: shift.worker_color,
                                    }"
                                >
                                    <div class="font-semibold">
                                        {{ shift.start_time }}–{{
                                            shift.end_time
                                        }}
                                    </div>
                                    <div class="mt-0.5 truncate">
                                        {{ shift.worker_name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </main>
</template>
