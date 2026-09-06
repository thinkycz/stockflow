import type { Transaction } from './useBankReview';

export function sameTransaction(
    row: Transaction,
    saved: Transaction | undefined,
): boolean {
    if (!saved || !row.id) return false;
    return (Object.keys(row) as (keyof Transaction)[]).every(
        (key) => row[key] === saved[key],
    );
}

/** Restore only a well-formed draft for the exact server version it was based on. */
export function restoreReviewDraft(
    raw: string | null,
    saved: Transaction[],
): Transaction[] | null {
    if (!raw) return null;
    try {
        const value: unknown = JSON.parse(raw);
        if (
            !value ||
            typeof value !== 'object' ||
            !('baseline' in value) ||
            value.baseline !== JSON.stringify(saved) ||
            !('rows' in value) ||
            !Array.isArray(value.rows)
        )
            return null;
        const stringFields = [
            'booked_on',
            'item_type',
            'amount',
            'currency',
            'category',
        ];
        const nullableFields = [
            'executed_on',
            'counterparty_name',
            'counterparty_account',
            'variable_symbol',
            'constant_symbol',
            'specific_symbol',
            'description',
            'sales_from',
            'sales_to',
            'review_note',
        ];
        const rows: Transaction[] = [];
        for (const row of value.rows as unknown[]) {
            if (!row || typeof row !== 'object') return null;
            const fields = row as Record<string, unknown>;
            if (
                !stringFields.every((key) => typeof fields[key] === 'string') ||
                !nullableFields.every(
                    (key) =>
                        fields[key] === null || typeof fields[key] === 'string',
                ) ||
                typeof fields.manually_edited !== 'boolean' ||
                (fields.id !== undefined &&
                    (typeof fields.id !== 'number' ||
                        !Number.isSafeInteger(fields.id)))
            )
                return null;
            rows.push(row as Transaction);
        }
        return rows;
    } catch {
        return null;
    }
}
