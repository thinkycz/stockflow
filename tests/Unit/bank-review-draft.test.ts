import { describe, expect, test } from 'vitest';
import {
    restoreReviewDraft,
    sameTransaction,
} from '@/features/bank-statements/review-draft';
import type { Transaction } from '@/features/bank-statements/useBankReview';

const saved: Transaction = {
    id: 1,
    booked_on: '2026-08-15',
    executed_on: null,
    item_type: 'Synthetic Wolt',
    amount: '210.00',
    currency: 'CZK',
    counterparty_name: null,
    counterparty_account: null,
    variable_symbol: null,
    constant_symbol: null,
    specific_symbol: null,
    description: null,
    category: 'wolt',
    sales_from: null,
    sales_to: null,
    review_note: null,
    manually_edited: false,
};

describe('bank review draft recovery', () => {
    test('preserves inferred dates, manual amounts, additions and deletions for the same server version', () => {
        const rows = [
            {
                ...saved,
                sales_from: '2026-08-01',
                sales_to: '2026-08-02',
                amount: '209.99',
            },
            { ...saved, id: undefined },
        ];
        expect(
            restoreReviewDraft(
                JSON.stringify({ baseline: JSON.stringify([saved]), rows }),
                [saved],
            ),
        ).toEqual(rows);
        expect(
            restoreReviewDraft(
                JSON.stringify({ baseline: JSON.stringify([saved]), rows: [] }),
                [saved],
            ),
        ).toEqual([]);
    });
    test('never restores a stale draft over a newer saved version', () => {
        expect(
            restoreReviewDraft(
                JSON.stringify({
                    baseline: JSON.stringify([saved]),
                    rows: [saved],
                }),
                [{ ...saved, amount: '200.00' }],
            ),
        ).toBeNull();
    });
    test('ignores malformed browser storage', () => {
        for (const raw of [
            null,
            '{',
            '{}',
            JSON.stringify({ baseline: JSON.stringify([saved]), rows: [null] }),
            JSON.stringify({
                baseline: JSON.stringify([saved]),
                rows: [{ ...saved, amount: {} }],
            }),
        ]) {
            expect(restoreReviewDraft(raw, [saved])).toBeNull();
        }
    });
    test('changed rows cannot display saved reconciliation results', () => {
        expect(sameTransaction({ ...saved }, saved)).toBe(true);
        for (const change of [
            { sales_from: '2026-08-01' },
            { amount: '200.00' },
            { category: 'bolt' },
            { booked_on: '2026-08-16' },
            { id: undefined },
        ]) {
            expect(sameTransaction({ ...saved, ...change }, saved)).toBe(false);
        }
    });
});
