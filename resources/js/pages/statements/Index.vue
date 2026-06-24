<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Eraser, Save } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatCzechDate } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';
import { formatMoney, formatMonth } from '@/lib/format';

type DayRow = {
    id: number | null;
    date: string;
    cash: number;
    card: number;
    wolt: number;
    bolt: number;
    bolt_cash: number;
    foodora: number;
    total: number;
};

const props = defineProps<{
    statement: {
        id: number;
        store_id: number;
        year: number;
        month: number;
    } | null;
    stores: Array<{ id: number; name: string }>;
    days: DayRow[];
    filters: {
        store_id: number | null;
        year: number;
        month: number;
    };
}>();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

const form = useForm<{ days: DayRow[] }>({
    days: props.days.map((day) => ({ ...day })),
});

const editing = reactive<Record<number, DayRow>>(
    Object.fromEntries(
        props.days.map((day) => [day.id ?? day.date, { ...day }]),
    ),
);

const submitting = ref(false);
const clearing = ref(false);

const months = computed(() => {
    const now = new Date();
    const result: Array<{ value: string; label: string }> = [];
    for (let offset = 0; offset < 12; offset++) {
        const date = new Date(now.getFullYear(), now.getMonth() - offset, 1);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const label = formatMonth(year, Number(month), locale.value);
        result.push({ value: `${year}-${month}`, label });
    }
    return result;
});

const monthValue = computed(() => {
    const m = String(props.filters.month).padStart(2, '0');
    return `${props.filters.year}-${m}`;
});

function selectMonth(value: string | number | null | undefined): void {
    const raw = value === null || value === undefined ? '' : String(value);
    const [year, month] = raw.split('-').map((part: string) => Number(part));
    if (!year || !month) {
        return;
    }
    router.get(
        route('statements.index'),
        {
            store_id: props.filters.store_id,
            year,
            month,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function selectStore(value: string | number | null | undefined): void {
    const storeId =
        value === null || value === undefined ? null : String(value);
    router.get(
        route('statements.index'),
        {
            store_id: storeId,
            year: props.filters.year,
            month: props.filters.month,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function rowTotal(row: DayRow): number {
    return (
        Number(row.cash || 0) +
        Number(row.card || 0) +
        Number(row.wolt || 0) +
        Number(row.bolt || 0) +
        Number(row.bolt_cash || 0) +
        Number(row.foodora || 0)
    );
}

const totals = computed(() => {
    let cash = 0;
    let card = 0;
    let wolt = 0;
    let bolt = 0;
    let boltCash = 0;
    let foodora = 0;
    let total = 0;
    for (const day of props.days) {
        cash += Number(day.cash || 0);
        card += Number(day.card || 0);
        wolt += Number(day.wolt || 0);
        bolt += Number(day.bolt || 0);
        boltCash += Number(day.bolt_cash || 0);
        foodora += Number(day.foodora || 0);
        total += rowTotal(day);
    }
    return {
        cash,
        card,
        wolt,
        bolt,
        bolt_cash: boltCash,
        foodora,
        total,
    };
});

function updateEditing(key: number, field: keyof DayRow, value: string): void {
    const day = editing[key];
    if (!day) {
        return;
    }
    const numeric = field === 'date' ? 0 : Number(value);
    if (field === 'date') {
        day.date = value;
    } else {
        (day as unknown as Record<string, number>)[field] = Number.isFinite(
            numeric,
        )
            ? numeric
            : 0;
    }
    day.total = rowTotal(day);
}

function save(): void {
    if (!props.statement) {
        return;
    }
    submitting.value = true;
    form.days = Object.values(editing);
    form.put(route('statements.update', { statement: props.statement.id }), {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function clearStatement(): void {
    if (!props.statement) {
        return;
    }
    if (!window.confirm(t('statements.actions.clear_confirm'))) {
        return;
    }
    clearing.value = true;
    router.post(
        route('statements.clear', { statement: props.statement.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                clearing.value = false;
            },
        },
    );
}

const hasNoStores = computed(() => props.stores.length === 0);
</script>

<template>
    <AppLayout :title="t('statements.title')">
        <Head :title="t('statements.title')" />

        <div class="flex flex-col gap-6">
            <div>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('statements.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('statements.subtitle') }}
                </p>
            </div>

            <Card padded>
                <div class="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
                    <div class="space-y-2">
                        <label
                            for="statement_store_id"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('statements.store') }}
                        </label>
                        <Select
                            id="statement_store_id"
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
                            :placeholder="t('statements.select_store')"
                            :disabled="hasNoStores"
                            @update:model-value="selectStore"
                        />
                    </div>
                    <div class="space-y-2">
                        <label
                            for="statement_month"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('statements.month') }}
                        </label>
                        <Select
                            id="statement_month"
                            :model-value="monthValue"
                            :options="months"
                            @update:model-value="selectMonth"
                        />
                    </div>
                </div>
            </Card>

            <div
                v-if="!props.statement"
                class="rounded-2xl border border-outline-glass bg-surface-container-lowest p-8 text-center"
            >
                <p class="text-sm font-semibold text-on-surface">
                    {{ t('statements.empty.title') }}
                </p>
                <p class="mt-1 text-xs text-on-surface-variant">
                    {{ t('statements.empty.description') }}
                </p>
            </div>

            <template v-else>
                <Card padded>
                    <div class="overflow-x-auto">
                        <DataTable class="[&_td]:px-2 [&_th]:px-2">
                            <thead>
                                <tr>
                                    <th class="min-w-[6rem]">
                                        {{ t('statements.columns.day') }}
                                    </th>
                                    <th class="min-w-[7rem] text-right">
                                        {{ t('statements.columns.cash') }}
                                    </th>
                                    <th class="min-w-[7rem] text-right">
                                        {{ t('statements.columns.card') }}
                                    </th>
                                    <th class="min-w-[7rem] text-right">
                                        {{ t('statements.columns.wolt') }}
                                    </th>
                                    <th class="min-w-[7rem] text-right">
                                        {{ t('statements.columns.bolt') }}
                                    </th>
                                    <th class="min-w-[7rem] text-right">
                                        {{ t('statements.columns.bolt_cash') }}
                                    </th>
                                    <th class="min-w-[7rem] text-right">
                                        {{ t('statements.columns.foodora') }}
                                    </th>
                                    <th class="min-w-[7rem] text-right">
                                        {{ t('statements.columns.total') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="day in props.days"
                                    :key="day.id ?? day.date"
                                >
                                    <td
                                        class="font-mono text-xs text-on-surface-variant"
                                    >
                                        {{ formatCzechDate(day.date) }}
                                    </td>
                                    <td class="text-right">
                                        <Input
                                            :model-value="String(day.cash || 0)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="text-right"
                                            @update:model-value="
                                                (value) =>
                                                    updateEditing(
                                                        day.id ?? 0,
                                                        'cash',
                                                        String(value),
                                                    )
                                            "
                                        />
                                    </td>
                                    <td class="text-right">
                                        <Input
                                            :model-value="String(day.card || 0)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="text-right"
                                            @update:model-value="
                                                (value) =>
                                                    updateEditing(
                                                        day.id ?? 0,
                                                        'card',
                                                        String(value),
                                                    )
                                            "
                                        />
                                    </td>
                                    <td class="text-right">
                                        <Input
                                            :model-value="String(day.wolt || 0)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="text-right"
                                            @update:model-value="
                                                (value) =>
                                                    updateEditing(
                                                        day.id ?? 0,
                                                        'wolt',
                                                        String(value),
                                                    )
                                            "
                                        />
                                    </td>
                                    <td class="text-right">
                                        <Input
                                            :model-value="String(day.bolt || 0)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="text-right"
                                            @update:model-value="
                                                (value) =>
                                                    updateEditing(
                                                        day.id ?? 0,
                                                        'bolt',
                                                        String(value),
                                                    )
                                            "
                                        />
                                    </td>
                                    <td class="text-right">
                                        <Input
                                            :model-value="
                                                String(day.bolt_cash || 0)
                                            "
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="text-right"
                                            @update:model-value="
                                                (value) =>
                                                    updateEditing(
                                                        day.id ?? 0,
                                                        'bolt_cash',
                                                        String(value),
                                                    )
                                            "
                                        />
                                    </td>
                                    <td class="text-right">
                                        <Input
                                            :model-value="
                                                String(day.foodora || 0)
                                            "
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="text-right"
                                            @update:model-value="
                                                (value) =>
                                                    updateEditing(
                                                        day.id ?? 0,
                                                        'foodora',
                                                        String(value),
                                                    )
                                            "
                                        />
                                    </td>
                                    <td
                                        class="text-right font-semibold text-on-surface"
                                    >
                                        {{ formatMoney(rowTotal(day)) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th
                                        class="border-t border-outline-glass pt-2 text-left text-xs font-semibold text-on-surface-variant"
                                    >
                                        Σ
                                    </th>
                                    <th
                                        class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                    >
                                        {{ formatMoney(totals.cash) }}
                                    </th>
                                    <th
                                        class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                    >
                                        {{ formatMoney(totals.card) }}
                                    </th>
                                    <th
                                        class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                    >
                                        {{ formatMoney(totals.wolt) }}
                                    </th>
                                    <th
                                        class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                    >
                                        {{ formatMoney(totals.bolt) }}
                                    </th>
                                    <th
                                        class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                    >
                                        {{ formatMoney(totals.bolt_cash) }}
                                    </th>
                                    <th
                                        class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                    >
                                        {{ formatMoney(totals.foodora) }}
                                    </th>
                                    <th
                                        class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface"
                                    >
                                        {{ formatMoney(totals.total) }}
                                    </th>
                                </tr>
                            </tfoot>
                        </DataTable>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                    >
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="clearing"
                            @click="clearStatement"
                        >
                            <Eraser :size="14" />
                            {{ t('statements.actions.clear') }}
                        </Button>
                        <Button
                            type="button"
                            :disabled="submitting"
                            @click="save"
                        >
                            <Save :size="14" />
                            {{ t('statements.actions.save') }}
                        </Button>
                    </div>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
