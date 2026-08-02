export type GiftVoucherStatus = 'active' | 'expired' | 'redeemed' | 'voided';

export type GiftVoucherEvent = {
    type: 'issued' | 'redeemed' | 'voided' | 'redemption_reversed';
    reason: string | null;
    created_at: string;
};

export type GiftVoucherRow = {
    id: number;
    code: string;
    status: GiftVoucherStatus;
    redeemed_at: string | null;
    redeemed_store_id: number | null;
    events: GiftVoucherEvent[];
};

export type GiftVoucherBatch = {
    id: number;
    quantity: number;
    amount: number;
    expires_at: string | null;
    brand_name: string;
    created_at: string;
    counts: Record<GiftVoucherStatus, number>;
    vouchers: GiftVoucherRow[];
};

export type GiftVoucherLookup = {
    voucher_id: number;
    ticket: string;
    amount: number;
    expires_at: string | null;
    status: GiftVoucherStatus;
    code_suffix: string;
};
