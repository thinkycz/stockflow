export type ReceiptChannel = 'card' | 'wolt' | 'bolt' | 'foodora';
export type Receipt = {
    transaction_id: number;
    statement_id: number;
    channel: ReceiptChannel;
    booked_on: string;
    from: string;
    to: string;
    state: 'verified' | 'review';
    check: {
        actual: string;
        expected: string | null;
        difference: string | null;
        tolerance: string | null;
        reason: string | null;
    };
};
export type ReceiptCells = Record<
    string,
    Partial<Record<ReceiptChannel, Receipt[]>>
>;
type SalesDay = {
    date: string;
    card: number;
    wolt: number;
    bolt: number;
    bolt_cash: number;
    foodora: number;
};

export function receiptPending(
    receipt: Receipt,
    saved: SalesDay[],
    edited: SalesDay[],
): boolean {
    const baseline = new Map(saved.map((day) => [day.date, day]));
    return edited.some((day) => {
        if (day.date < receipt.from || day.date > receipt.to) return false;
        const original = baseline.get(day.date);
        return (
            !original ||
            Number(day[receipt.channel]) !==
                Number(original[receipt.channel]) ||
            (receipt.channel === 'bolt' &&
                Number(day.bolt_cash) !== Number(original.bolt_cash))
        );
    });
}
