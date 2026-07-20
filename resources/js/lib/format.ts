import { getIntlLocale } from '@/i18n';

export function formatMoney(value: number): string {
    return new Intl.NumberFormat(getIntlLocale(), {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

export function formatSignedMoney(value: number): string {
    return new Intl.NumberFormat(getIntlLocale(), {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        signDisplay: 'exceptZero',
    }).format(value);
}

export function formatNumber(value: number, fractionDigits = 0): string {
    return new Intl.NumberFormat(getIntlLocale(), {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(value);
}

export function formatStockQuantity(value: number): string {
    return new Intl.NumberFormat(getIntlLocale(), {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }).format(value);
}

export function formatSignedNumber(value: number): string {
    return new Intl.NumberFormat(getIntlLocale(), {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
        signDisplay: 'exceptZero',
    }).format(value);
}

/**
 * Format a date string as `dd.MM.yyyy`.
 *
 * The backend emits ISO 8601 and the frontend formats it using the UI locale.
 */
export function formatDate(date: string | Date | null | undefined): string {
    if (date === null || date === undefined || date === '') {
        return '—';
    }

    const parsed = date instanceof Date ? date : new Date(date);
    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat(getIntlLocale(), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'Europe/Prague',
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

    return new Intl.DateTimeFormat(getIntlLocale(), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: 'Europe/Prague',
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
