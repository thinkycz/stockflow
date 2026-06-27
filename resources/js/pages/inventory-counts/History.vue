<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import {
    formatCzechDate,
    formatCzechDateTime,
} from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';

type Session = {
    id: number;
    counted_at: string;
    note: string | null;
    created_by: number | null;
    created_by_email: string | null;
    item_count: number;
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    items: Array<{ id: number; title: string }>;
    rows: Session[];
    filters: {
        store_id: number | null;
        item_id: number | null;
        from: string;
        to: string;
    };
    is_admin: boolean;
}>();

const { t } = useI18n();
const route = useRoute();

useBoundLocale();

const fromInput = ref<string>(props.filters.from);
const toInput = ref<string>(props.filters.to);
let dateTimer: ReturnType<typeof setTimeout> | null = null;

function selectItem(value: string | number | null | undefined): void {
    const itemId =
        value === null || value === undefined || value === ''
            ? null
            : Number(value);
    router.get(
        route('inventory-counts.history'),
        {
            item_id: itemId,
            from: fromInput.value,
            to: toInput.value,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function applyRange(): void {
    router.get(
        route('inventory-counts.history'),
        {
            item_id: props.filters.item_id,
            from: fromInput.value,
            to: toInput.value,
        },
        { preserveState: true, preserveScroll: true },
    );
}

watch([fromInput, toInput], () => {
    if (dateTimer !== null) {
        clearTimeout(dateTimer);
    }
    dateTimer = setTimeout(applyRange, 300);
});

const totals = computed(() => ({
    count: props.rows.length,
}));
</script>

<template>
    <AppLayout :title="t('inventory_counts.history.title')">
        <Head :title="t('inventory_counts.history.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('inventory_counts.history.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('inventory_counts.history.subtitle') }}
                    </p>
                </div>
                <Link :href="route('inventory-counts.index')">
                    <Button variant="secondary">
                        ← {{ t('inventory_counts.title') }}
                    </Button>
                </Link>
            </header>

            <Card padded>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-2">
                        <label
                            for="history_item_id"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('inventory_counts.history.filter.item') }}
                        </label>
                        <Select
                            id="history_item_id"
                            :model-value="
                                props.filters.item_id !== null
                                    ? String(props.filters.item_id)
                                    : ''
                            "
                            :options="[
                                {
                                    value: '',
                                    label: t(
                                        'inventory_counts.history.filter.all_items',
                                    ),
                                },
                                ...items.map((i) => ({
                                    value: String(i.id),
                                    label: i.title,
                                })),
                            ]"
                            @update:model-value="selectItem"
                        />
                    </div>

                    <div class="space-y-2">
                        <label
                            for="history_from"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('inventory_counts.history.filter.from') }}
                        </label>
                        <Input
                            id="history_from"
                            v-model="fromInput"
                            type="date"
                        />
                    </div>
                    <div class="space-y-2">
                        <label
                            for="history_to"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('inventory_counts.history.filter.to') }}
                        </label>
                        <Input id="history_to" v-model="toInput" type="date" />
                    </div>
                </div>
            </Card>

            <Card padded>
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-on-surface-variant"
                >
                    <span>
                        <strong class="font-semibold text-on-surface">{{
                            totals.count
                        }}</strong>
                        {{ t('inventory_counts.history.sessions_label') }} ·
                        {{ formatCzechDate(props.filters.from) }} –
                        {{ formatCzechDate(props.filters.to) }}
                    </span>
                </div>

                <EmptyState
                    v-if="props.rows.length === 0"
                    :title="t('inventory_counts.history.empty.title')"
                    :description="
                        t('inventory_counts.history.empty.description')
                    "
                />

                <div v-else class="overflow-x-auto">
                    <DataTable class="[&_td]:px-2 [&_th]:px-2">
                        <thead>
                            <tr>
                                <th class="min-w-[10rem] text-left">
                                    {{
                                        t(
                                            'inventory_counts.history.columns.counted_at',
                                        )
                                    }}
                                </th>
                                <th class="min-w-[8rem] text-right">
                                    {{
                                        t(
                                            'inventory_counts.history.columns.item_count',
                                        )
                                    }}
                                </th>
                                <th class="min-w-[14rem] text-left">
                                    {{
                                        t(
                                            'inventory_counts.history.columns.note',
                                        )
                                    }}
                                </th>
                                <th class="min-w-[14rem] text-left">
                                    {{
                                        t(
                                            'inventory_counts.history.columns.created_by',
                                        )
                                    }}
                                </th>
                                <th class="min-w-[8rem] text-right">
                                    {{
                                        t(
                                            'inventory_counts.history.columns.open',
                                        )
                                    }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.rows"
                                :key="row.id"
                                class="border-b border-outline-glass/40 last:border-b-0"
                            >
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ formatCzechDateTime(row.counted_at) }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ row.item_count }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ row.note ?? '—' }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ row.created_by_email ?? '—' }}
                                </td>
                                <td class="text-right">
                                    <Link
                                        :href="
                                            route('inventory-counts.show', {
                                                session: row.id,
                                            })
                                        "
                                    >
                                        <Button variant="secondary">
                                            {{
                                                t(
                                                    'inventory_counts.history.open',
                                                )
                                            }}
                                            →
                                        </Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
