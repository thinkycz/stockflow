export function formatMoney(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

export function formatNumber(value: number, fractionDigits = 0): string {
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(value);
}

export function formatDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(date),
    );
}

export function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

/**
 * Format a year/month pair (e.g. 2026, 6) as a localized month name like
 * "červen 2026". Uses the caller-supplied locale so the rendering matches
 * the active i18n locale instead of the browser default.
 */
export function formatMonth(year: number, month: number, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'long',
    }).format(new Date(year, month - 1, 1));
}
