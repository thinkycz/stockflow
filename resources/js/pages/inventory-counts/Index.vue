<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
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
    quantity: number;
    note: string;
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    stores: Array<{ id: number; name: string }>;
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
                quantity: row.current,
                note: '',
            },
        ]),
    ),
);

const form = useForm<{
    store_id: number | null;
    rows: EditableRow[];
}>({
    store_id: props.filters.store_id,
    rows: Object.values(editing),
});

const submitting = ref(false);

const hasNoStores = computed(() => props.stores.length === 0);
const hasNoItems = computed(() => props.rows.length === 0);

const totals = computed(() => {
    let quantity = 0;

    for (const row of props.rows) {
        quantity += row.current;
    }

    return { quantity };
});

function selectStore(value: string | number | null | undefined): void {
    const storeId =
        value === null || value === undefined ? null : String(value);
    router.get(
        route('inventory-counts.index'),
        { store_id: storeId },
        { preserveState: true, preserveScroll: true },
    );
}

function updateEditing(
    itemId: number,
    field: keyof EditableRow,
    value: string,
): void {
    const row = editing[itemId];
    if (!row) {
        return;
    }
    if (field === 'quantity') {
        const numeric = Number(value);
        row.quantity =
            Number.isFinite(numeric) && numeric >= 0 ? Math.floor(numeric) : 0;
    } else {
        row.note = value;
    }
}

function formatNumber(value: number, fractionDigits = 0): string {
    return value.toLocaleString(undefined, {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}

function save(): void {
    if (!props.store) {
        return;
    }
    submitting.value = true;
    form.store_id = props.store.id;
    form.rows = Object.values(editing);
    form.post(route('inventory-counts.update'), {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
        },
    });
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

            <Card padded>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div v-if="is_admin" class="w-full max-w-sm space-y-2">
                        <label
                            for="inventory_store_id"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('inventory_counts.store') }}
                        </label>
                        <Select
                            id="inventory_store_id"
                            :model-value="
                                props.filters.store_id !== null
                                    ? String(props.filters.store_id)
                                    : null
                            "
                            :options="
                                props.stores.map((s) => ({
                                    value: String(s.id),
                                    label: s.name,
                                }))
                            "
                            :placeholder="t('inventory_counts.select_store')"
                            :disabled="hasNoStores"
                            @update:model-value="selectStore"
                        />
                    </div>
                    <div v-else-if="props.store" class="text-sm">
                        <p
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('inventory_counts.store') }}
                        </p>
                        <p class="font-semibold text-on-surface">
                            {{ props.store.name }}
                        </p>
                    </div>
                </div>
            </Card>

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
                                <th class="min-w-[12rem] text-left">
                                    {{ t('inventory_counts.columns.item') }}
                                </th>
                                <th class="min-w-[8rem] text-left">
                                    {{ t('inventory_counts.columns.sku') }}
                                </th>
                                <th class="min-w-[5rem] text-left">
                                    {{ t('inventory_counts.columns.unit') }}
                                </th>
                                <th class="min-w-[8rem] text-right">
                                    {{ t('inventory_counts.columns.current') }}
                                </th>
                                <th class="min-w-[8rem] text-right">
                                    {{ t('inventory_counts.columns.previous') }}
                                </th>
                                <th class="min-w-[9rem] text-right">
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
                                <td class="font-semibold text-on-surface">
                                    {{ row.title }}
                                </td>
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ row.sku ?? '–' }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ row.unit ?? '–' }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ row.current }}
                                </td>
                                <td
                                    class="text-right text-xs text-on-surface-variant"
                                >
                                    {{ row.previous ?? '–' }}
                                </td>
                                <td class="text-right">
                                    <Input
                                        :model-value="
                                            String(
                                                editing[row.item_id]
                                                    ?.quantity ?? 0,
                                            )
                                        "
                                        type="number"
                                        step="1"
                                        min="0"
                                        class="text-right"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    row.item_id,
                                                    'quantity',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                                <td>
                                    <Input
                                        :model-value="
                                            editing[row.item_id]?.note ?? ''
                                        "
                                        type="text"
                                        @update:model-value="
                                            (value) =>
                                                updateEditing(
                                                    row.item_id,
                                                    'note',
                                                    String(value),
                                                )
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th
                                    colspan="3"
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
                    <Button type="button" :disabled="submitting" @click="save">
                        <Save :size="14" />
                        {{ t('inventory_counts.actions.save') }}
                    </Button>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
