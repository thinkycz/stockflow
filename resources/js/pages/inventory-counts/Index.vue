<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Minus, Plus, Save, XCircle } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Input from '@/components/ui/Input.vue';
import Modal from '@/components/ui/Modal.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatNumber as formatLocalizedNumber } from '@/lib/format';

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
    classification: string;
    classificationTouched: boolean;
    note: string;
    clientVersion: number;
};

type Draft = {
    id: number;
    started_at: string;
    rows: Array<{
        item_id: number;
        quantity: number;
        classification: string | null;
        note: string | null;
        client_version: number;
    }>;
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    rows: InventoryRow[];
    filters: { store_id: number | null };
    is_admin: boolean;
    default_counted_on: string;
    classifications: string[];
    draft: Draft | null;
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const editing = reactive<Record<number, EditableRow>>(
    Object.fromEntries(
        props.rows.map((row) => {
            const saved = props.draft?.rows.find(
                (draftRow) => draftRow.item_id === row.item_id,
            );
            return [
                row.item_id,
                {
                    item_id: row.item_id,
                    quantity: saved ? String(saved.quantity) : '',
                    classification: saved?.classification ?? 'consumption',
                    classificationTouched: saved?.classification !== null,
                    note: saved?.note ?? '',
                    clientVersion: saved?.client_version ?? 0,
                },
            ];
        }),
    ),
);

const submitting = ref(false);
const cancelling = ref(false);
const cancelModalOpen = ref(false);
const inventoryDate = ref(props.default_counted_on);
const saveState = reactive<
    Record<number, 'idle' | 'saving' | 'saved' | 'error'>
>({});
const pending = new Set<Promise<unknown>>();

const hasNoItems = computed(() => props.rows.length === 0);

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
    row.quantity = next;
    if (!row.classificationTouched) {
        const source = props.rows.find((item) => item.item_id === itemId);
        row.classification =
            source && numeric > source.current
                ? 'inventory_correction'
                : 'consumption';
    }
}

function difference(itemId: number): number {
    const editable = editing[itemId];
    const source = props.rows.find((item) => item.item_id === itemId);
    if (!editable || !source || editable.quantity === '') {
        return 0;
    }
    return Number(editable.quantity) - source.current;
}

function classificationOptions(
    itemId: number,
): Array<{ value: string; label: string }> {
    const values =
        difference(itemId) > 0
            ? ['inventory_correction', 'initial_stock', 'other']
            : ['consumption', 'damaged', 'stolen', 'missing', 'other'];
    return values.map((value) => ({
        value,
        label: t(`stock_movements.reasons.${value}`),
    }));
}

function setClassification(
    itemId: number,
    value: string | number | null | undefined,
): void {
    const row = editing[itemId];
    if (!row || value === null || value === undefined) {
        return;
    }
    row.classification = String(value);
    row.classificationTouched = true;
}

function adjustQuantity(itemId: number, delta: number): void {
    const row = editing[itemId];
    const source = props.rows.find((item) => item.item_id === itemId);
    if (!row || !source) {
        return;
    }
    const current = row.quantity === '' ? source.current : Number(row.quantity);
    const next = Math.max(0, current + delta);
    setQuantity(itemId, next);
    if (delta > 0 && next > source.current) {
        row.classification = 'inventory_correction';
    } else if (delta < 0 && next < source.current) {
        row.classification = 'consumption';
    }
    autosave(itemId);
}

function focusAdjacentQuantity(event: KeyboardEvent, itemId: number): void {
    const currentIndex = props.rows.findIndex((row) => row.item_id === itemId);
    const nextRow = props.rows[currentIndex + (event.shiftKey ? -1 : 1)];
    if (!nextRow) {
        return;
    }

    event.preventDefault();
    const input = document.querySelector<HTMLInputElement>(
        `[data-testid="qty-${nextRow.item_id}"]`,
    );
    input?.focus();
    input?.select();
}

function setNote(itemId: number, value: string | number | undefined): void {
    const row = editing[itemId];
    if (!row) {
        return;
    }
    row.note = value === null || value === undefined ? '' : String(value);
}

function formatWithUnit(value: number | null, unit: string | null): string {
    const base = value === null ? '–' : formatLocalizedNumber(value, 3);
    return unit !== null ? `${base} ${unit}` : base;
}

function startDraft(): void {
    if (!props.store) {
        return;
    }
    router.post(route('inventory-counts.drafts.start'), {
        store_id: props.store.id,
    });
}

function autosave(itemId: number): void {
    const row = editing[itemId];
    if (!props.draft || !row || row.quantity === '') {
        return;
    }

    row.clientVersion += 1;
    saveState[itemId] = 'saving';
    const request = window.axios
        .put(route('inventory-counts.drafts.rows.update', props.draft.id), {
            item_id: row.item_id,
            quantity: row.quantity,
            classification:
                difference(itemId) === 0 ? null : row.classification,
            note: row.note,
            client_version: row.clientVersion,
        })
        .then(() => {
            saveState[itemId] = 'saved';
        })
        .catch(() => {
            saveState[itemId] = 'error';
        })
        .finally(() => pending.delete(request));
    pending.add(request);
}

async function save(): Promise<void> {
    if (!props.draft || !hasAnyValue.value || inventoryDate.value === '') {
        return;
    }
    submitting.value = true;
    Object.values(editing).forEach((row) => autosave(row.item_id));
    await Promise.all([...pending]);
    if (Object.values(saveState).includes('error')) {
        submitting.value = false;
        return;
    }
    router.post(
        route('inventory-counts.drafts.close', props.draft.id),
        { counted_on: inventoryDate.value },
        {
            onFinish: () => (submitting.value = false),
        },
    );
}

function openCancelModal(): void {
    cancelModalOpen.value = true;
}

function closeCancelModal(): void {
    if (!cancelling.value) {
        cancelModalOpen.value = false;
    }
}

async function cancelDraft(): Promise<void> {
    if (!props.draft || cancelling.value) {
        return;
    }

    cancelling.value = true;
    await Promise.allSettled([...pending]);
    router.post(
        route('inventory-counts.drafts.cancel', props.draft.id),
        {},
        {
            onSuccess: () => (cancelModalOpen.value = false),
            onFinish: () => (cancelling.value = false),
        },
    );
}
</script>

<template>
    <AppLayout :title="t('inventory_counts.title')">
        <Head :title="t('inventory_counts.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('inventory_counts.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('inventory_counts.subtitle') }}
                    </p>
                    <StoreContextIndicator />
                </div>
                <Link :href="route('inventory-counts.history')">
                    <Button variant="secondary">
                        {{ t('inventory_counts.history.title') }} →
                    </Button>
                </Link>
            </header>

            <Card v-if="props.store" padded>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <CalendarDays :size="19" />
                        </span>
                        <div>
                            <label
                                for="inventory_counted_on"
                                class="text-sm font-semibold text-on-surface"
                            >
                                {{ t('inventory_counts.date.label') }}
                            </label>
                            <p class="mt-1 text-xs text-on-surface-variant">
                                {{ t('inventory_counts.date.help') }}
                            </p>
                        </div>
                    </div>
                    <Input
                        id="inventory_counted_on"
                        v-model="inventoryDate"
                        type="date"
                        :max="props.default_counted_on"
                        :disabled="submitting"
                        class="w-full sm:w-48"
                    />
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

            <section v-else class="space-y-4">
                <div v-if="!draft" class="py-8 text-center">
                    <Button type="button" @click="startDraft">
                        {{ t('inventory_counts.actions.start') }}
                    </Button>
                </div>
                <DataTable v-if="draft" density="compact">
                    <thead>
                        <tr>
                            <th class="min-w-[14rem] text-left">
                                {{ t('inventory_counts.columns.item') }}
                            </th>
                            <th class="min-w-[9rem] text-left">
                                {{ t('inventory_counts.columns.stock_levels') }}
                            </th>
                            <th class="min-w-[16rem] text-right">
                                {{ t('inventory_counts.columns.new_quantity') }}
                            </th>
                            <th class="min-w-[12rem] text-left">
                                {{ t('inventory_counts.columns.reason') }}
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
                            <td>
                                <dl class="space-y-1.5">
                                    <div>
                                        <dt
                                            class="text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant"
                                        >
                                            {{
                                                t(
                                                    'inventory_counts.columns.current_short',
                                                )
                                            }}
                                        </dt>
                                        <dd
                                            class="font-semibold text-on-surface"
                                        >
                                            {{
                                                formatWithUnit(
                                                    row.current,
                                                    row.unit,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant"
                                        >
                                            {{
                                                t(
                                                    'inventory_counts.columns.previous_short',
                                                )
                                            }}
                                        </dt>
                                        <dd
                                            class="text-xs text-on-surface-variant"
                                        >
                                            {{
                                                formatWithUnit(
                                                    row.previous,
                                                    row.unit,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </td>
                            <td class="text-right">
                                <div
                                    class="inline-flex flex-col items-end gap-1"
                                >
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
                                            inputmode="decimal"
                                            step="0.001"
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
                                            @blur="autosave(row.item_id)"
                                            @keydown.tab="
                                                focusAdjacentQuantity(
                                                    $event,
                                                    row.item_id,
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
                                    <span
                                        class="text-[10px] font-semibold"
                                        :class="
                                            difference(row.item_id) < 0
                                                ? 'text-rose-600'
                                                : difference(row.item_id) > 0
                                                  ? 'text-emerald-600'
                                                  : 'text-on-surface-variant'
                                        "
                                    >
                                        {{
                                            t(
                                                'inventory_counts.columns.difference',
                                            )
                                        }}:
                                        {{
                                            difference(row.item_id) > 0
                                                ? '+'
                                                : ''
                                        }}{{ difference(row.item_id) }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <Select
                                    v-if="difference(row.item_id) !== 0"
                                    :model-value="
                                        editing[row.item_id]?.classification
                                    "
                                    :options="
                                        classificationOptions(row.item_id)
                                    "
                                    @update:model-value="
                                        setClassification(row.item_id, $event)
                                    "
                                    @blur="autosave(row.item_id)"
                                />
                                <span
                                    v-else
                                    class="text-xs text-on-surface-variant"
                                    >—</span
                                >
                            </td>
                            <td>
                                <Input
                                    :model-value="
                                        editing[row.item_id]?.note ?? ''
                                    "
                                    type="text"
                                    @update:model-value="
                                        (value) => setNote(row.item_id, value)
                                    "
                                    @blur="autosave(row.item_id)"
                                />
                                <span
                                    class="mt-1 block text-[10px] text-on-surface-variant"
                                >
                                    {{
                                        t(
                                            `inventory_counts.autosave.${saveState[row.item_id] ?? 'idle'}`,
                                        )
                                    }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>

                <div
                    v-if="draft"
                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="submitting || cancelling"
                        @click="openCancelModal"
                    >
                        <XCircle :size="14" />
                        {{ t('inventory_counts.actions.cancel') }}
                    </Button>
                    <Button
                        type="button"
                        :disabled="
                            submitting ||
                            cancelling ||
                            !hasAnyValue ||
                            inventoryDate === ''
                        "
                        @click="save"
                    >
                        <Save :size="14" />
                        {{ t('inventory_counts.actions.save') }}
                    </Button>
                </div>
            </section>

            <Modal
                :open="cancelModalOpen"
                :title="t('inventory_counts.cancel_modal.title')"
                @close="closeCancelModal"
            >
                <p class="text-sm leading-6 text-on-surface-variant">
                    {{ t('inventory_counts.cancel_modal.description') }}
                </p>

                <template #footer>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="cancelling"
                        @click="closeCancelModal"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="button"
                        variant="danger"
                        :disabled="cancelling"
                        @click="cancelDraft"
                    >
                        <XCircle :size="14" />
                        {{ t('inventory_counts.cancel_modal.confirm') }}
                    </Button>
                </template>
            </Modal>
        </div>
    </AppLayout>
</template>
