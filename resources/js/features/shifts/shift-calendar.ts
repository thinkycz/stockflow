import type {
    Worker,
    Shift,
    ShiftRequest,
    CalendarDay,
    CalendarShift,
    CalendarRequest,
} from './scheduling-types';

type TimedShift = {
    id: number;
    start_time: string;
    end_time: string;
};

export function sortShiftsByTime<T extends TimedShift>(
    shifts: readonly T[],
): T[] {
    return [...shifts].sort(
        (left, right) =>
            left.start_time.localeCompare(right.start_time) ||
            left.end_time.localeCompare(right.end_time) ||
            left.id - right.id,
    );
}

export function buildCalendarDays(
    year: number,
    month: number,
    workers: readonly Worker[],
    shifts: readonly Shift[],
    requests: readonly ShiftRequest[],
): CalendarDay[] {
    const workerMap = new Map<number, Worker>();
    for (const w of workers) {
        workerMap.set(w.id, w);
    }

    const shiftsByDate = new Map<string, CalendarShift[]>();
    for (const shift of shifts) {
        const worker = workerMap.get(shift.worker_id);
        const enriched: CalendarShift = {
            ...shift,
            worker_name: worker
                ? `${worker.first_name} ${worker.last_name}`
                : '?',
            worker_color: worker?.color ?? '#64748B',
        };
        const list = shiftsByDate.get(shift.date) ?? [];
        list.push(enriched);
        shiftsByDate.set(shift.date, list);
    }

    for (const [date, shifts] of shiftsByDate) {
        shiftsByDate.set(date, sortShiftsByTime(shifts));
    }

    const requestsByDate = new Map<string, CalendarRequest[]>();
    for (const shiftRequest of requests) {
        const worker = workerMap.get(shiftRequest.worker_id);
        const enriched: CalendarRequest = {
            ...shiftRequest,
            worker_name: worker
                ? `${worker.first_name} ${worker.last_name}`
                : '?',
            worker_color: worker?.color ?? '#64748B',
        };
        const list = requestsByDate.get(shiftRequest.date) ?? [];
        list.push(enriched);
        requestsByDate.set(shiftRequest.date, list);
    }

    for (const [date, requests] of requestsByDate) {
        requestsByDate.set(date, sortShiftsByTime(requests));
    }

    return calendarMonthDays(year, month).map((day) => ({
        ...day,
        shifts: (shiftsByDate.get(day.date) ?? []).map((shift) => ({
            ...shift,
        })),
        requests: (requestsByDate.get(day.date) ?? []).map((request) => ({
            ...request,
        })),
    }));
}

export function calendarMonthDays(
    year: number,
    month: number,
): Array<{ date: string; day: number; isCurrentMonth: boolean }> {
    const first = new Date(year, month - 1, 1);
    const leadingDays = (first.getDay() + 6) % 7;
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(year, month - 1, 1 - leadingDays + index);
        return {
            date: formatDateKey(date),
            day: date.getDate(),
            isCurrentMonth: date.getMonth() === month - 1,
        };
    });
}

function formatDateKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
