import { useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import {
    movementDisplayLabelKey,
    type MovementDisplayLabelKey,
} from '@/features/stock-movements/movement-display';

type StoreOption = {
    id: number;
    name: string;
    is_warehouse: boolean;
};

export type ItemOption = {
    id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    available_quantity?: number;
    warehouse_quantity?: number;
    quantities_by_store?: Record<string, number>;
    purchase_price?: number;
};

type Row = {
    id: string;
    item_id: string;
    quantity: string;
    quantity_after: string;
    adjustment_reason: string;
};

type FormState = {
    mode: 'transfer' | 'adjustment' | 'consumption' | 'incoming';
    store_id: string;
    source_store_id: string;
    note: string;
    occurred_at: string;
};

type StockMovementPayload = {
    mode: 'transfer' | 'adjustment' | 'consumption' | 'incoming';
    store_id: number | string | null;
    source_store_id?: number | string | null;
    note: string | null;
    occurred_at?: string | null;
    items: Array<{
        item_id: string;
        quantity?: string;
        quantity_after?: string;
        adjustment_reason?: string;
    }>;
};
export type MovementDraftProps = {
    stores: StoreOption[];
    items: ItemOption[];
    reasons: string[];
    classifications: string[];
    is_admin: boolean;
    defaults: {
        mode: 'transfer' | 'adjustment' | 'consumption' | 'incoming';
        item_id: string | null;
        warehouse_id: number;
    };
};

export function useMovementDraft(props: MovementDraftProps) {
    const { t } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const defaultWarehouseId = String(
        props.defaults.mode === 'consumption' ||
            props.defaults.mode === 'incoming'
            ? (props.stores[0]?.id ?? '')
            : (props.defaults.warehouse_id ?? ''),
    );

    const form = useForm<FormState>({
        mode: props.defaults.mode || 'transfer',
        store_id: defaultWarehouseId,
        source_store_id: '',
        note: '',
        occurred_at: '',
    });

    const rows = reactive<Row[]>([]);

    const isAdjustmentMode = computed(
        (): boolean => form.mode === 'adjustment',
    );

    const isConsumptionMode = computed(
        (): boolean => form.mode === 'consumption',
    );

    const isIncomingMode = computed((): boolean => form.mode === 'incoming');

    const pageTitle = computed((): string => {
        if (isAdjustmentMode.value)
            return t('stock_movements.title_adjustment');
        if (isConsumptionMode.value)
            return t('stock_movements.title_consumption');
        if (isIncomingMode.value) return t('stock_movements.title_incoming');
        return t('stock_movements.title_create');
    });

    const pageSubtitle = computed((): string => {
        if (isAdjustmentMode.value)
            return t('stock_movements.subtitle_adjustment');
        if (isConsumptionMode.value)
            return t('stock_movements.subtitle_consumption');
        if (isIncomingMode.value) return t('stock_movements.subtitle_incoming');
        return t('stock_movements.subtitle_create');
    });

    const destinationStoreOptions = computed((): StoreOption[] => {
        if (!form.source_store_id) {
            return props.stores;
        }
        return props.stores.filter(
            (store) => String(store.id) !== form.source_store_id,
        );
    });

    const inferredLabelKey = computed((): MovementDisplayLabelKey | null => {
        if (isAdjustmentMode.value) {
            return 'adjustment';
        }
        if (isConsumptionMode.value) {
            return 'consumption';
        }
        if (isIncomingMode.value) {
            return 'incoming';
        }
        if (!form.store_id) {
            return null;
        }
        if (!form.source_store_id) {
            return 'incoming';
        }

        const sourceStore = props.stores.find(
            (store) => String(store.id) === form.source_store_id,
        );
        const destinationStore = props.stores.find(
            (store) => String(store.id) === form.store_id,
        );
        return movementDisplayLabelKey(
            'transfer',
            sourceStore ?? null,
            destinationStore ?? null,
        );
    });

    const isOutgoingTransfer = computed(
        (): boolean =>
            !isAdjustmentMode.value &&
            !isConsumptionMode.value &&
            !isIncomingMode.value &&
            form.source_store_id !== '' &&
            form.store_id !== '',
    );

    const removesStock = computed(
        (): boolean => isConsumptionMode.value || isOutgoingTransfer.value,
    );

    const serverError = ref<string | null>(null);

    let rowCounter = 0;

    function makeRow(): Row {
        rowCounter += 1;

        return {
            id: `row-${rowCounter}`,
            item_id: props.defaults.item_id
                ? String(props.defaults.item_id)
                : '',
            quantity: '',
            quantity_after: '0.000',
            adjustment_reason: props.reasons[0] ?? 'other',
        };
    }

    function ensureFirstRow(): void {
        if (rows.length === 0) {
            rows.push(makeRow());
        }
    }

    ensureFirstRow();

    function addRow(): void {
        rows.push(makeRow());
    }

    function removeRow(id: string): void {
        const filtered = rows.filter((row) => row.id !== id);
        rows.splice(0, rows.length, ...filtered);
        if (rows.length === 0) {
            rows.push(makeRow());
        }
    }

    const itemMap = computed(() => {
        const map: Record<number, ItemOption> = {};
        for (const item of selectedItemsCache.value) {
            map[item.id] = item;
        }
        for (const item of searchResults.value) {
            map[item.id] = item;
        }
        for (const item of props.items) {
            map[item.id] = item;
        }
        return map;
    });

    const searchResults = ref<ItemOption[]>([]);

    const searchLoading = ref<boolean>(false);

    const selectedItemsCache = ref<ItemOption[]>([]);

    let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

    let searchAbortController: AbortController | null = null;

    function searchItems(term: string): void {
        if (searchDebounceTimer !== null) {
            clearTimeout(searchDebounceTimer);
        }
        if (term.trim() === '') {
            searchResults.value = [];
            searchLoading.value = false;
            return;
        }
        searchLoading.value = true;
        searchDebounceTimer = setTimeout(() => {
            if (searchAbortController !== null) {
                searchAbortController.abort();
            }
            searchAbortController = new AbortController();
            const signal = searchAbortController.signal;
            window.axios
                .get(route('items.search', { q: term, mode: form.mode }), {
                    signal,
                })
                .then((response: { data: { items: ItemOption[] } }) => {
                    if (!signal.aborted) {
                        searchResults.value = response.data.items;
                    }
                })
                .catch(() => {
                    if (!signal.aborted) {
                        searchResults.value = [];
                    }
                })
                .finally(() => {
                    if (!signal.aborted) {
                        searchLoading.value = false;
                    }
                });
        }, 200);
    }

    const availableItems = computed((): ItemOption[] => {
        const seen = new Set<number>();
        const result: ItemOption[] = [];
        for (const item of selectedItemsCache.value) {
            if (!seen.has(item.id)) {
                seen.add(item.id);
                result.push(item);
            }
        }
        for (const item of searchResults.value) {
            if (!seen.has(item.id)) {
                seen.add(item.id);
                result.push(item);
            }
        }
        for (const item of props.items) {
            if (!seen.has(item.id)) {
                seen.add(item.id);
                result.push(item);
            }
        }
        return result;
    });

    function onItemSelect(row: Row, item: ItemOption | null): void {
        if (item === null || item.id === 0) {
            return;
        }
        const existing = selectedItemsCache.value.findIndex(
            (i) => i.id === item.id,
        );
        if (existing === -1) {
            selectedItemsCache.value.push(item);
        } else {
            selectedItemsCache.value[existing] = item;
        }
        onItemChange(row);
    }

    const reasonOptions = computed(() =>
        props.reasons.map((r) => ({
            value: r,
            label: t(`stock_movements.reasons.${r}`),
        })),
    );

    function findItem(id: string): ItemOption | null {
        const numId = Number(id);
        if (!numId) {
            return null;
        }
        return itemMap.value[numId] ?? null;
    }

    function rowUnitPrice(row: Row): number {
        return Number(findItem(row.item_id)?.purchase_price ?? 0);
    }

    function quantityAtStore(item: ItemOption, storeId: string): number {
        if (!storeId) {
            return 0;
        }
        if (!props.is_admin) {
            return Number(item.available_quantity ?? 0);
        }
        return Number(item.quantities_by_store?.[storeId] ?? 0);
    }

    const activeStockStoreId = computed((): string => {
        if (isOutgoingTransfer.value) {
            return form.source_store_id;
        }
        return form.store_id;
    });

    function displayedQuantity(row: Row): number {
        const item = findItem(row.item_id);
        if (!item) {
            return 0;
        }
        return quantityAtStore(item, activeStockStoreId.value);
    }

    function onItemChange(row: Row): void {
        if (isAdjustmentMode.value) {
            const item = findItem(row.item_id);
            if (item) {
                row.quantity_after = String(displayedQuantity(row));
            }
        }
    }

    function lineTotal(row: Row): number {
        if (isAdjustmentMode.value) {
            return Math.abs(difference(row)) * rowUnitPrice(row);
        }
        return Number(row.quantity || 0) * rowUnitPrice(row);
    }

    function remainingQuantity(row: Row): number {
        return Math.max(0, displayedQuantity(row) - Number(row.quantity || 0));
    }

    function difference(row: Row): number {
        return Number(row.quantity_after || 0) - displayedQuantity(row);
    }

    const totals = computed(() => {
        let quantity = 0;
        let value = 0;
        for (const row of rows) {
            if (isAdjustmentMode.value) {
                quantity += Math.abs(difference(row));
                value += lineTotal(row);
            } else {
                quantity += Number(row.quantity || 0);
                value += lineTotal(row);
            }
        }
        return { quantity, value };
    });

    function isOutOfStockError(row: Row): boolean {
        if (!removesStock.value) {
            return false;
        }
        return Number(row.quantity || 0) > displayedQuantity(row);
    }

    const hasOutOfStockErrors = computed(() => rows.some(isOutOfStockError));

    const outOfStockRows = computed(() => rows.filter(isOutOfStockError));

    function buildPayload(data: FormState): StockMovementPayload {
        const items = rows.map((row) => {
            if (isAdjustmentMode.value) {
                return {
                    item_id: row.item_id,
                    quantity_after: row.quantity_after,
                    adjustment_reason: row.adjustment_reason,
                };
            }
            return {
                item_id: row.item_id,
                quantity: row.quantity,
            };
        });

        if (isAdjustmentMode.value) {
            return {
                mode: 'adjustment',
                store_id: data.store_id || null,
                note: data.note || null,
                occurred_at: data.occurred_at || null,
                items,
            };
        }

        if (isConsumptionMode.value) {
            return {
                mode: 'consumption',
                store_id: data.store_id || null,
                note: data.note || null,
                occurred_at: data.occurred_at || null,
                items,
            };
        }

        if (isIncomingMode.value) {
            return {
                mode: 'incoming',
                store_id: data.store_id || null,
                note: data.note || null,
                items,
            };
        }

        return {
            mode: 'transfer',
            store_id: data.store_id || null,
            source_store_id: data.source_store_id || null,
            note: data.note || null,
            occurred_at: data.occurred_at || null,
            items,
        };
    }

    function submit(): void {
        if (hasOutOfStockErrors.value) {
            return;
        }
        serverError.value = null;
        form.transform((data) => buildPayload(data)).post(
            route('stock-movements.store'),
            {
                onError: (errors): void => {
                    const firstKey = Object.keys(errors)[0];
                    if (firstKey) {
                        serverError.value = String(errors[firstKey]);
                    }
                },
            },
        );
    }

    watch(
        () => [form.store_id, form.source_store_id, form.mode],
        (): void => {
            if (
                isOutgoingTransfer.value &&
                form.store_id === form.source_store_id
            ) {
                form.store_id = '';
            }
        },
    );
    return {
        t,
        route,
        form,
        rows,
        isAdjustmentMode,
        isConsumptionMode,
        isIncomingMode,
        pageTitle,
        pageSubtitle,
        destinationStoreOptions,
        inferredLabelKey,
        isOutgoingTransfer,
        removesStock,
        serverError,
        addRow,
        removeRow,
        searchLoading,
        searchItems,
        availableItems,
        onItemSelect,
        reasonOptions,
        findItem,
        displayedQuantity,
        lineTotal,
        remainingQuantity,
        difference,
        totals,
        isOutOfStockError,
        hasOutOfStockErrors,
        outOfStockRows,
        submit,
    };
}
