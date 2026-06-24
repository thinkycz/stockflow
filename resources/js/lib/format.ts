export function formatMoney(value: number): string {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

export function formatNumber(value: number, fractionDigits = 0): string {
    return new Intl.NumberFormat('cs-CZ', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(value);
}

/**
 * Format a date string as `dd.MM.yyyy`.
 *
 * The application presents dates exclusively in the Czech format
 * regardless of the active UI locale — the backend always emits ISO 8601
 * and the frontend applies this fixed formatter.
 */
export function formatDate(date: string | Date | null | undefined): string {
    if (date === null || date === undefined || date === '') {
        return '—';
    }

    const parsed = date instanceof Date ? date : new Date(date);
    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('cs-CZ', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(parsed);
}

/**
 * Format a date-time string as `dd.MM.yyyy HH:mm`.
 */
export function formatDateTime(date: string | Date | null | undefined): string {
    if (date === null || date === undefined || date === '') {
        return '—';
    }

    const parsed = date instanceof Date ? date : new Date(date);
    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('cs-CZ', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(parsed);
}

/**
 * Format a year/month pair (e.g. 2026, 6) as a localized month name like
 * "červen 2026". Uses the caller-supplied locale so the rendering matches
 * the active i18n locale instead of the browser default.
 */
export function formatMonth(
    year: number,
    month: number,
    locale: string,
): string {
    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'long',
    }).format(new Date(year, month - 1, 1));
}
