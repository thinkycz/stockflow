<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Combobox from '@/components/ui/Combobox.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MovementTypeBadge from '@/components/ui/MovementTypeBadge.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatMoney, formatNumber } from '@/lib/format';

type StoreOption = {
    id: number;
    name: string;
    is_warehouse: boolean;
};
type ItemOption = {
    id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    warehouse_quantity: number;
    quantities_by_store: Record<string, number>;
    purchase_price: number;
};

type Row = {
    id: string;
    item_id: string;
    quantity: string;
    quantity_after: string;
    adjustment_reason: string;
};

type FormState = {
    mode: 'transfer' | 'adjustment' | 'consumption';
    store_id: string;
    source_store_id: string;
    note: string;
    occurred_at: string;
};

const props = defineProps<{
    stores: StoreOption[];
    items: ItemOption[];
    reasons: string[];
    classifications: string[];
    is_admin: boolean;
    defaults: {
        mode: 'transfer' | 'adjustment' | 'consumption';
        item_id: string | null;
        warehouse_id: number;
    };
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const defaultWarehouseId = String(
    props.defaults.mode === 'consumption'
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

const isAdjustmentMode = computed((): boolean => form.mode === 'adjustment');
const isConsumptionMode = computed((): boolean => form.mode === 'consumption');

const destinationStoreOptions = computed((): StoreOption[] => {
    if (!form.source_store_id) {
        return props.stores;
    }
    return props.stores.filter(
        (store) => String(store.id) !== form.source_store_id,
    );
});

type InferredLabelKey = 'incoming' | 'transfer' | 'adjustment' | 'consumption';

const inferredLabelKey = computed((): InferredLabelKey | null => {
    if (isAdjustmentMode.value) {
        return 'adjustment';
    }
    if (isConsumptionMode.value) {
        return 'consumption';
    }
    if (!form.store_id) {
        return null;
    }
    if (!form.source_store_id) {
        return 'incoming';
    }
    return 'transfer';
});

const isOutgoingTransfer = computed(
    (): boolean =>
        !isAdjustmentMode.value &&
        !isConsumptionMode.value &&
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
        item_id: props.defaults.item_id ? String(props.defaults.item_id) : '',
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
            .get(route('items.search', { q: term }), { signal })
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
    return Number(item.quantities_by_store[storeId] ?? 0);
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

type StockMovementPayload = {
    mode: 'transfer' | 'adjustment' | 'consumption';
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
</script>

<template>
    <AppLayout :title="t('stock_movements.title_create')">
        <Head :title="t('stock_movements.title_create')" />

        <div class="flex flex-col gap-6">
            <div>
                <Link
                    v-if="props.is_admin"
                    :href="route('stock-movements.index')"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-primary"
                >
                    <ArrowLeft :size="12" />
                    {{ t('stock_movements.back_to_list') }}
                </Link>
            </div>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{
                            isAdjustmentMode
                                ? t('stock_movements.title_adjustment')
                                : isConsumptionMode
                                  ? t('stock_movements.title_consumption')
                                  : t('stock_movements.title_create')
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{
                            isAdjustmentMode
                                ? t('stock_movements.subtitle_adjustment')
                                : isConsumptionMode
                                  ? t('stock_movements.subtitle_consumption')
                                  : t('stock_movements.subtitle_create')
                        }}
                    </p>
                </div>
                <div v-if="props.is_admin" class="flex items-center gap-2">
                    <Link
                        v-if="isAdjustmentMode"
                        :href="route('stock-movements.create')"
                    >
                        <Button variant="secondary" type="button">
                            {{ t('stock_movements.back_to_transfer') }}
                        </Button>
                    </Link>
                    <Link
                        v-else-if="!isConsumptionMode"
                        :href="
                            route('stock-movements.create', {
                                mode: 'adjustment',
                            })
                        "
                    >
                        <Button variant="secondary" type="button">
                            {{ t('stock_movements.open_adjustment') }}
                        </Button>
                    </Link>
                    <Link
                        v-if="!isConsumptionMode"
                        :href="
                            route('stock-movements.create', {
                                mode: 'consumption',
                            })
                        "
                    >
                        <Button variant="secondary" type="button">
                            {{ t('stock_movements.open_consumption') }}
                        </Button>
                    </Link>
                    <Link v-else :href="route('stock-movements.create')">
                        <Button variant="secondary" type="button">
                            {{ t('stock_movements.back_to_transfer') }}
                        </Button>
                    </Link>
                </div>
            </div>

            <Alert v-if="serverError" variant="error">
                {{ serverError }}
            </Alert>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <Card padded>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <template
                            v-if="!isAdjustmentMode && !isConsumptionMode"
                        >
                            <div class="space-y-2">
                                <Label for="source_store_id">{{
                                    t('stock_movements.form.source_store')
                                }}</Label>
                                <Select
                                    id="source_store_id"
                                    v-model="form.source_store_id"
                                    :options="[
                                        {
                                            value: '',
                                            label: t(
                                                'stock_movements.form.no_source',
                                            ),
                                        },
                                        ...stores.map((s) => ({
                                            value: String(s.id),
                                            label: s.name,
                                        })),
                                    ]"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="store_id" :required="true">{{
                                    t('stock_movements.form.destination_store')
                                }}</Label>
                                <Select
                                    id="store_id"
                                    v-model="form.store_id"
                                    :options="[
                                        {
                                            value: '',
                                            label: t(
                                                'stock_movements.form.select_store',
                                            ),
                                        },
                                        ...destinationStoreOptions.map((s) => ({
                                            value: String(s.id),
                                            label: s.name,
                                        })),
                                    ]"
                                    required
                                />
                            </div>
                            <div
                                v-if="inferredLabelKey"
                                class="space-y-2 sm:col-span-2"
                            >
                                <Label>{{
                                    t('stock_movements.form.inferred_type')
                                }}</Label>
                                <div class="flex h-10 items-center">
                                    <MovementTypeBadge
                                        :type="inferredLabelKey"
                                        :label-key="inferredLabelKey"
                                    />
                                </div>
                            </div>
                        </template>
                        <div v-else class="space-y-2 sm:col-span-2">
                            <Label for="adjustment_store_id" :required="true">{{
                                t(
                                    isConsumptionMode
                                        ? 'stock_movements.form.consumption_store'
                                        : 'stock_movements.form.adjustment_store',
                                )
                            }}</Label>
                            <Select
                                id="adjustment_store_id"
                                v-model="form.store_id"
                                :options="[
                                    {
                                        value: '',
                                        label: t(
                                            'stock_movements.form.select_store',
                                        ),
                                    },
                                    ...stores.map((s) => ({
                                        value: String(s.id),
                                        label: s.name,
                                    })),
                                ]"
                                required
                            />
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <Label for="note">{{
                            t('stock_movements.form.note')
                        }}</Label>
                        <Input id="note" v-model="form.note" type="text" />
                    </div>
                    <div v-if="props.is_admin" class="mt-4 space-y-2">
                        <Label for="occurred_at">{{
                            t('stock_movements.form.occurred_at')
                        }}</Label>
                        <Input
                            id="occurred_at"
                            v-model="form.occurred_at"
                            type="datetime-local"
                        />
                        <p class="text-xs text-on-surface-variant">
                            {{ t('stock_movements.form.occurred_at_help') }}
                        </p>
                    </div>
                </Card>

                <Card padded>
                    <CardHeader class="mb-3">
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <CardTitle>
                                {{ t('stock_movements.form.items') }}
                            </CardTitle>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-on-surface-variant"
                            >
                                <span>
                                    <span
                                        class="font-semibold text-on-surface"
                                        >{{ rows.length }}</span
                                    >
                                    {{ t('stock_movements.summary.rows') }}
                                </span>
                                <span class="text-outline-glass">·</span>
                                <span>
                                    <span
                                        class="font-semibold text-on-surface"
                                        >{{
                                            formatNumber(totals.quantity)
                                        }}</span
                                    >
                                    {{ t('stock_movements.summary.quantity') }}
                                </span>
                                <span class="text-outline-glass">·</span>
                                <span>
                                    <span
                                        class="font-semibold text-on-surface"
                                        >{{ formatMoney(totals.value) }}</span
                                    >
                                    {{ t('stock_movements.summary.value') }}
                                </span>
                            </div>
                        </div>
                    </CardHeader>

                    <div class="overflow-x-auto">
                        <DataTable class="[&_td]:px-2 [&_th]:px-2">
                            <thead>
                                <tr>
                                    <th class="min-w-[14rem]">
                                        {{ t('stock_movements.form.item') }}
                                    </th>
                                    <th class="min-w-[6rem] text-right">
                                        {{
                                            t(
                                                'stock_movements.form.current_quantity',
                                            )
                                        }}
                                    </th>
                                    <th
                                        v-if="!isAdjustmentMode"
                                        class="min-w-[7rem]"
                                    >
                                        {{
                                            t(
                                                removesStock
                                                    ? 'stock_movements.form.quantity_out'
                                                    : 'stock_movements.form.quantity_in',
                                            )
                                        }}
                                    </th>
                                    <th v-else class="min-w-[7rem]">
                                        {{
                                            t(
                                                'stock_movements.form.quantity_after',
                                            )
                                        }}
                                    </th>
                                    <th
                                        v-if="removesStock"
                                        class="min-w-[6rem] text-right"
                                    >
                                        {{
                                            t('stock_movements.form.remaining')
                                        }}
                                    </th>
                                    <th
                                        v-if="isAdjustmentMode"
                                        class="min-w-[6rem] text-right"
                                    >
                                        {{
                                            t('stock_movements.form.difference')
                                        }}
                                    </th>
                                    <th
                                        v-if="isAdjustmentMode"
                                        class="min-w-[9rem]"
                                    >
                                        {{ t('stock_movements.form.reason') }}
                                    </th>
                                    <th
                                        v-if="!isAdjustmentMode"
                                        class="min-w-[6rem] text-right"
                                    >
                                        {{
                                            t('stock_movements.form.line_total')
                                        }}
                                    </th>
                                    <th class="w-0"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.id">
                                    <td>
                                        <Combobox
                                            v-model="row.item_id"
                                            :items="availableItems"
                                            :loading="searchLoading"
                                            :placeholder="
                                                t(
                                                    'stock_movements.form.select_item',
                                                )
                                            "
                                            required
                                            @search="searchItems"
                                            @select="
                                                (item) =>
                                                    onItemSelect(
                                                        row,
                                                        item as unknown as ItemOption,
                                                    )
                                            "
                                        />
                                    </td>
                                    <td
                                        class="text-right text-on-surface-variant"
                                    >
                                        {{
                                            findItem(row.item_id)
                                                ? formatNumber(
                                                      displayedQuantity(row),
                                                  )
                                                : '—'
                                        }}
                                    </td>
                                    <td v-if="!isAdjustmentMode">
                                        <Input
                                            v-model="row.quantity"
                                            type="number"
                                            step="0.001"
                                            min="1"
                                            :invalid="isOutOfStockError(row)"
                                            required
                                        />
                                    </td>
                                    <td v-else>
                                        <Input
                                            v-model="row.quantity_after"
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            required
                                        />
                                    </td>
                                    <td
                                        v-if="isOutgoingTransfer"
                                        class="text-right text-on-surface-variant"
                                    >
                                        {{
                                            formatNumber(remainingQuantity(row))
                                        }}
                                    </td>
                                    <td
                                        v-if="isAdjustmentMode"
                                        class="text-right font-semibold"
                                        :class="
                                            difference(row) >= 0
                                                ? 'text-emerald-600'
                                                : 'text-rose-600'
                                        "
                                    >
                                        {{ formatNumber(difference(row)) }}
                                    </td>
                                    <td v-if="isAdjustmentMode">
                                        <Select
                                            v-model="row.adjustment_reason"
                                            :options="reasonOptions"
                                            required
                                        />
                                    </td>
                                    <td
                                        v-if="!isAdjustmentMode"
                                        class="text-right font-semibold text-on-surface"
                                    >
                                        {{ formatMoney(lineTotal(row)) }}
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-on-surface-variant transition hover:bg-rose-50 hover:text-error-red"
                                            :aria-label="
                                                t(
                                                    'stock_movements.form.remove_row',
                                                )
                                            "
                                            @click="removeRow(row.id)"
                                        >
                                            <Trash2 :size="14" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </DataTable>
                    </div>

                    <div v-if="hasOutOfStockErrors" class="mt-3 space-y-1">
                        <div
                            v-for="row in outOfStockRows"
                            :key="row.id"
                            class="rounded-lg border border-error-red/30 bg-error-red/5 p-2 text-xs text-error-red"
                        >
                            <span class="font-semibold">
                                {{ findItem(row.item_id)?.title ?? '—' }}:
                            </span>
                            {{ t('stock_movements.errors.out_of_stock') }}
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Button
                            type="button"
                            variant="secondary"
                            @click="addRow"
                        >
                            <Plus :size="14" />
                            {{ t('stock_movements.form.add_row') }}
                        </Button>
                        <div class="flex items-center gap-3">
                            <Link
                                v-if="props.is_admin"
                                :href="route('stock-movements.index')"
                            >
                                <Button variant="secondary" type="button">
                                    {{ t('common.cancel') }}
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                :disabled="
                                    form.processing || hasOutOfStockErrors
                                "
                            >
                                {{ t('stock_movements.form.save') }}
                            </Button>
                        </div>
                    </div>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
