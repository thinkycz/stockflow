/**
 * Czech date formatting helpers.
 *
 * The application presents dates exclusively in the Czech `dd.MM.yyyy`
 * format (with `dd.MM.yyyy HH:mm` for timestamps) regardless of the
 * active UI locale. The backend always emits ISO 8601 strings so we
 * format on the frontend using `Intl.DateTimeFormat` with a fixed
 * locale tag.
 *
 * Use `formatCzechDate`, `formatCzechDateTime`, or
 * `formatCzechDateRange` rather than `Date.toLocaleDateString()` —
 * that relies on the browser locale and produces inconsistent output.
 */

const EMPTY = '—';

const dateFormatter = new Intl.DateTimeFormat('cs-CZ', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('cs-CZ', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

/**
 * Parse a backend date string (ISO 8601 or `YYYY-MM-DD`) into a Date.
 */
function parseDate(value: string | Date | null | undefined): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Format the given value as `dd.MM.yyyy`, or `—` when it is missing or
 * unparseable.
 */
export function formatCzechDate(
    value: string | Date | null | undefined,
): string {
    const date = parseDate(value);

    if (date === null) {
        return EMPTY;
    }

    return dateFormatter.format(date);
}

/**
 * Format the given value as `dd.MM.yyyy HH:mm`, or `—` when it is
 * missing or unparseable.
 */
export function formatCzechDateTime(
    value: string | Date | null | undefined,
): string {
    const date = parseDate(value);

    if (date === null) {
        return EMPTY;
    }

    return dateTimeFormatter.format(date);
}

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
