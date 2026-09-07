import { expect, test } from 'vitest';
import {
    receiptPending,
    type Receipt,
} from '@/features/statements/receipt-status';

const receipt: Receipt = {
    transaction_id: 1,
    statement_id: 1,
    channel: 'bolt',
    from: '2026-07-31',
    to: '2026-08-02',
    booked_on: '2026-08-04',
    state: 'verified',
    check: {
        actual: '100.00',
        expected: '100.00',
        difference: '0.00',
        tolerance: '5.00',
        reason: null,
    },
};
const saved = ['2026-07-31', '2026-08-01', '2026-08-02', '2026-08-03'].map(
    (date) => ({
        date,
        card: 100,
        wolt: 100,
        bolt: 100,
        bolt_cash: 40,
        foodora: 100,
    }),
);

test('a Bolt Cash edit invalidates the whole covered Bolt period, not other channels', () => {
    const edited = saved.map((day, i) =>
        i === 0 ? { ...day, bolt_cash: 45 } : day,
    );
    expect(receiptPending(receipt, saved, edited)).toBe(true);
    expect(receiptPending({ ...receipt, channel: 'wolt' }, saved, edited)).toBe(
        false,
    );
});
test('out of period edits do not invalidate a receipt and saving or reverting restores it', () => {
    expect(
        receiptPending(
            receipt,
            saved,
            saved.map((day, i) => (i === 3 ? { ...day, bolt: 200 } : day)),
        ),
    ).toBe(false);
    const edited = saved.map((day, i) =>
        i === 1 ? { ...day, bolt: 200 } : day,
    );
    expect(receiptPending(receipt, saved, edited)).toBe(true);
    expect(receiptPending(receipt, edited, edited)).toBe(false);
    expect(receiptPending(receipt, saved, saved)).toBe(false);
});
