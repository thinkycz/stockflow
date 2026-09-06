import { formatDate, formatDateTime } from '@/lib/format';

export const formatCzechDate = formatDate;
export const formatCzechDateTime = formatDateTime;

/**
 * Format a date range, collapsing it to a single day when `from` and
 * `to` fall on the same calendar date.
 */
export function formatCzechDateRange(
    from: string | Date,
    to: string | Date,
): string {
    const fromText = formatCzechDate(from);
    const toText = formatCzechDate(to);

    if (fromText === toText) {
        return fromText;
    }

    return `${fromText} – ${toText}`;
}

/**
 * Vue composable that exposes the Czech date helpers through the
 * `useCzechDate()` factory. Components that only need a couple of
 * calls can import the standalone functions instead.
 */
export function useCzechDate(): {
    formatCzechDate: typeof formatCzechDate;
    formatCzechDateTime: typeof formatCzechDateTime;
    formatCzechDateRange: typeof formatCzechDateRange;
} {
    return {
        formatCzechDate,
        formatCzechDateTime,
        formatCzechDateRange,
    };
}
