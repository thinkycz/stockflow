<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Minus, Plus, Save } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Input from '@/components/ui/Input.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

type InventoryRow = {
    item_id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    current: number;
    previous: number | null;
};

type EditableRow = {
    item_id: number;
    /**
     * Quantity being entered by the user. An empty string means the
     * user has not entered anything for this row and the existing
     * on-hand quantity must stay untouched on save.
     */
    quantity: string;
    note: string;
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    rows: InventoryRow[];
    filters: { store_id: number | null };
    is_admin: boolean;
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const editing = reactive<Record<number, EditableRow>>(
    Object.fromEntries(
        props.rows.map((row) => [
            row.item_id,
            {
                item_id: row.item_id,
                quantity: '',
                note: '',
            },
        ]),
    ),
);

const submitting = ref(false);

const hasNoItems = computed(() => props.rows.length === 0);

const totals = computed(() => {
    let quantity = 0;

    for (const row of props.rows) {
        quantity += row.current;
    }

    return { quantity };
});

const hasAnyValue = computed(() =>
    Object.values(editing).some((row) => row.quantity !== ''),
);

function setQuantity(itemId: number, value: string | number | undefined): void {
    const row = editing[itemId];
    if (!row) {
        return;
    }
    const next = value === null || value === undefined ? '' : String(value);
    if (next === '') {
        row.quantity = '';
        return;
    }
    const numeric = Number(next);
    if (!Number.isFinite(numeric) || numeric < 0) {
        return;
    }
    row.quantity = String(Math.floor(numeric));
}

function adjustQuantity(itemId: number, delta: number): void {
    const row = editing[itemId];
    if (!row) {
        return;
    }
    const current = row.quantity === '' ? 0 : Number(row.quantity);
    const next = Math.max(0, current + delta);
    row.quantity = String(next);
}

function setNote(itemId: number, value: string | number | undefined): void {
    const row = editing[itemId];
    if (!row) {
        return;
    }
    row.note = value === null || value === undefined ? '' : String(value);
}

function formatNumber(value: number, fractionDigits = 0): string {
    return value.toLocaleString('cs-CZ', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}

function formatWithUnit(value: number | null, unit: string | null): string {
    const base = value === null ? '–' : formatNumber(value);
    return unit !== null ? `${base} ${unit}` : base;
}

function save(): void {
    if (!props.store || !hasAnyValue.value) {
        return;
    }

    const rowsToSave = Object.values(editing)
        .filter((row) => row.quantity !== '')
        .map((row) => ({
            item_id: row.item_id,
            quantity: Number(row.quantity),
            note: row.note,
        }));

    submitting.value = true;
    router.post(
        route('inventory-counts.update'),
        {
            store_id: props.store.id,
            rows: rowsToSave,
        },
        {
            preserveScroll: true,
            onFinish: (): void => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :title="t('inventory_counts.title')">
        <Head :title="t('inventory_counts.title')" />

        <div class="flex flex-col gap-6">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('inventory_counts.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('inventory_counts.subtitle') }}
                    </p>
                </div>
                <Link :href="route('inventory-counts.history')">
                    <button
                        type="button"
                        class="rounded-xl border border-outline-glass bg-surface-container-lowest px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/5"
                    >
                        {{ t('inventory_counts.history.title') }} →
                    </button>
                </Link>
            </div>

            <div
                v-if="!props.store"
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-8 text-center"
            >
                <p class="text-sm font-semibold text-on-surface">
                    {{ t('inventory_counts.empty.title') }}
                </p>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ t('inventory_counts.empty.description') }}
                </p>
            </div>

            <div
                v-else-if="hasNoItems"
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-8 text-center"
            >
                <p class="text-sm font-semibold text-on-surface">
                    {{ t('inventory_counts.empty.no_items') }}
                </p>
            </div>

            <Card v-else padded>
                <div class="overflow-x-auto">
                    <DataTable class="[&_td]:px-2 [&_th]:px-2">
                        <thead>
                            <tr>
                                <th class="min-w-[16rem] text-left">
                                    {{ t('inventory_counts.columns.item') }}
                                </th>
                                <th class="min-w-[8rem] text-right">
                                    {{ t('inventory_counts.columns.current') }}
                                </th>
                                <th class="min-w-[8rem] text-right">
                                    {{ t('inventory_counts.columns.previous') }}
                                </th>
                                <th class="min-w-[16rem] text-right">
                                    {{
                                        t(
                                            'inventory_counts.columns.new_quantity',
                                        )
                                    }}
                                </th>
                                <th class="min-w-[12rem] text-left">
                                    {{ t('inventory_counts.columns.note') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in props.rows" :key="row.item_id">
                                <td>
                                    <div class="font-semibold text-on-surface">
                                        {{ row.title }}
                                    </div>
                                    <div
                                        v-if="row.sku"
                                        class="font-mono text-xs text-on-surface-variant"
                                    >
                                        {{ row.sku }}
                                    </div>
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatWithUnit(row.current, row.unit) }}
                                </td>
                                <td
                                    class="text-right text-xs text-on-surface-variant"
                                >
                                    {{ formatWithUnit(row.previous, row.unit) }}
                                </td>
                                <td class="text-right">
                                    <div
                                        class="inline-flex items-center justify-end gap-1"
                                    >
                                        <button
                                            type="button"
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-outline-glass bg-surface-container-lowest text-on-surface-variant transition hover:bg-primary/5 hover:text-primary active:scale-95"
                                            :aria-label="t('common.decrease')"
                                            :data-testid="`dec-${row.item_id}`"
                                            @click="
                                                adjustQuantity(row.item_id, -1)
                                            "
                                        >
                                            <Minus :size="14" />
                                        </button>
                                        <Input
                                            :model-value="
                                                editing[row.item_id]
                                                    ?.quantity ?? ''
                                            "
                                            type="number"
                                            inputmode="numeric"
                                            step="1"
                                            min="0"
                                            :placeholder="String(row.current)"
                                            :data-testid="`qty-${row.item_id}`"
                                            class="w-24 text-center"
                                            @update:model-value="
                                                (value) =>
                                                    setQuantity(
                                                        row.item_id,
                                                        value,
                                                    )
                                            "
                                        />
                                        <button
                                            type="button"
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-outline-glass bg-surface-container-lowest text-on-surface-variant transition hover:bg-primary/5 hover:text-primary active:scale-95"
                                            :aria-label="t('common.increase')"
                                            :data-testid="`inc-${row.item_id}`"
                                            @click="
                                                adjustQuantity(row.item_id, 1)
                                            "
                                        >
                                            <Plus :size="14" />
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <Input
                                        :model-value="
                                            editing[row.item_id]?.note ?? ''
                                        "
                                        type="text"
                                        @update:model-value="
                                            (value) =>
                                                setNote(row.item_id, value)
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th
                                    colspan="1"
                                    class="border-t border-outline-glass pt-2 text-left text-xs font-semibold text-on-surface-variant"
                                >
                                    {{
                                        t(
                                            'inventory_counts.totals.current_quantity',
                                        )
                                    }}
                                </th>
                                <th
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface"
                                >
                                    {{ formatNumber(totals.quantity) }}
                                </th>
                                <th
                                    colspan="3"
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                ></th>
                            </tr>
                        </tfoot>
                    </DataTable>
                </div>

                <div
                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                >
                    <Button
                        type="button"
                        :disabled="submitting || !hasAnyValue"
                        @click="save"
                    >
                        <Save :size="14" />
                        {{ t('inventory_counts.actions.save') }}
                    </Button>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
