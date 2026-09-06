import { formatDateInput, parseCzechDateInput } from '@/lib/date-input';
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

/** Render complete dates in Czech notation in every UI language. */
export function formatDate(value: string | Date | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const text = formatDateInput(value);
        return parseCzechDateInput(text) === value ? text : '—';
    }
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    const parts = new Intl.DateTimeFormat('cs-CZ', {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
        timeZone: 'Europe/Prague',
    }).formatToParts(date);
    const part = (type: string) =>
        parts.find((item) => item.type === type)?.value ?? '';
    return `${part('day')}.${part('month')}.${part('year')}`;
}

/** Render instants in Prague time with a 24-hour clock. */
export function formatDateTime(
    value: string | Date | null | undefined,
): string {
    const dateText = formatDate(value);
    if (dateText === '—' || value === null || value === undefined) return '—';
    return `${dateText} ${new Intl.DateTimeFormat('cs-CZ', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: 'Europe/Prague',
    }).format(value instanceof Date ? value : new Date(value))}`;
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
