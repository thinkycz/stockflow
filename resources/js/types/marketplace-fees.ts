export type MarketplaceFees = {
    estimate_type?: 'range' | 'point';
    transfer_min?: string;
    transfer_max?: string;
    net_min?: string;
    net_max?: string;
    transaction_fee?: string;
    transaction_vat?: string;
    excluded_items?: string[];
    segments?: {
        base: string;
        commission: string;
        vat: string;
        vat_rate: string;
    }[];
    base: string;
    commission_rate: string;
    vat_rate: string;
    commission: string;
    vat: string;
    deduction: string;
    net_revenue: string;
    expected_transfer: string;
};

export type EstimateComparison = {
    tolerance_min: string;
    tolerance_max: string;
    difference_min: string;
    difference_max: string;
    distance: string;
};
