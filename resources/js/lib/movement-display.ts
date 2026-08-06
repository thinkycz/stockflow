export type MovementType =
    | 'incoming'
    | 'transfer'
    | 'consumption'
    | 'adjustment'
    | 'inventory_reconciliation'
    | 'reversal';

export type MovementDisplayLabelKey = MovementType | 'outgoing';

type StoreKind = {
    is_warehouse: boolean;
};

export function movementDisplayLabelKey(
    type: MovementType,
    sourceStore: StoreKind | null = null,
    destinationStore: StoreKind | null = null,
): MovementDisplayLabelKey {
    if (
        type === 'transfer' &&
        sourceStore?.is_warehouse === true &&
        destinationStore?.is_warehouse === false
    ) {
        return 'outgoing';
    }

    return type;
}
