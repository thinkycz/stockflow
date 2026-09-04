import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { useRoute } from '@/composables/useRoute';
import {
    calendarMonthDays,
    sortShiftsByTime,
} from '@/features/shifts/shift-calendar';
import type { MonthlyShiftSummary } from '@/features/shifts/types';

type Shift = {
    id: number;
    worker_name: string;
    worker_color: string;
    date: string;
    start_time: string;
    end_time: string;
    attendance_rating: {
        state: 'future' | 'pending' | 'scored' | 'disabled';
        score: number | null;
        band: 'good' | 'warning' | 'poor' | null;
    };
};

type CalendarDay = {
    date: string;
    day: number;
    isCurrentMonth: boolean;
    shifts: Shift[];
    requests: [];
};
export type PublicShiftsProps = {
    store: { name: string };
    shifts: Shift[];
    monthly_summary: MonthlyShiftSummary[];
    filters: {
        year: number;
        month: number;
    };
    share_token: string;
};

export function usePublicShifts(props: PublicShiftsProps) {
    const { t, locale } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const { canInstall, iosBrowser, instructionsOpen, install } =
        usePwaInstall();

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
        const shiftsByDate = new Map<string, Shift[]>();

        for (const shift of props.shifts) {
            const shifts = shiftsByDate.get(shift.date) ?? [];
            shifts.push(shift);
            shiftsByDate.set(shift.date, shifts);
        }

        for (const [date, shifts] of shiftsByDate) {
            shiftsByDate.set(date, sortShiftsByTime(shifts));
        }

        return calendarMonthDays(props.filters.year, props.filters.month).map(
            (day) => ({
                ...day,
                shifts: shiftsByDate.get(day.date) ?? [],
                requests: [],
            }),
        );
    });

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
    return {
        t,
        route,
        canInstall,
        iosBrowser,
        instructionsOpen,
        install,
        currentMonthLabel,
        weekdayLabels,
        calendarDays,
        navigateMonth,
    };
}
