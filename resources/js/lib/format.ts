export function formatMoney(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

export function formatNumber(value: number, fractionDigits = 3): string {
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
