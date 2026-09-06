/** Calendar-only conversion; never apply a browser timezone to form values. */
export function parseCzechDateInput(
    value: string,
    withTime = false,
): string | null {
    const match = (
        withTime
            ? /^(\d{1,2})\.(\d{1,2})\.(\d{4}) (\d{1,2}):(\d{2})$/
            : /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/
    ).exec(value.trim());
    if (!match) return null;
    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const hour = Number(match[4] ?? 0);
    const minute = Number(match[5] ?? 0);
    const date = new Date(0);
    date.setUTCFullYear(year, month - 1, day);
    if (
        year < 1 ||
        date.getUTCFullYear() !== year ||
        date.getUTCMonth() !== month - 1 ||
        date.getUTCDate() !== day ||
        hour > 23 ||
        minute > 59
    )
        return null;
    const iso = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return withTime
        ? `${iso}T${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
        : iso;
}

export function formatDateInput(value: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?$/.exec(value);
    if (!match) return value;
    return `${Number(match[3])}.${Number(match[2])}.${match[1]}${match[4] === undefined ? '' : ` ${match[4]}:${match[5]}`}`;
}
