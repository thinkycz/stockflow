<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MovementTypeBadge from '@/components/ui/MovementTypeBadge.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import SearchFilter from '@/components/ui/SearchFilter.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatDate, formatMoney, formatSignedMoney } from '@/lib/format';

type MovementRow = {
    id: number;
    number: string;
    type:
        | 'incoming'
        | 'transfer'
        | 'consumption'
        | 'adjustment'
        | 'inventory_reconciliation'
        | 'reversal';
    display_label_key:
        | 'incoming'
        | 'outgoing'
        | 'transfer'
        | 'consumption'
        | 'adjustment'
        | 'inventory_reconciliation'
        | 'reversal';
    store_id: number | null;
    store_name: string | null;
    source_store_id: number | null;
    source_store_name: string | null;
    created_at: string;
    total_value: number;
    net_value: number;
    items_count: number;
    created_by: string | null;
};

type StoreOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    movements: MovementRow[];
    stores: StoreOption[];
    filters: {
        search: string;
        type: string | null;
        source_store_id: number | null;
        destination_store_id: number | null;
        date_from: string | null;
        date_to: string | null;
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const formSearch = ref<string>(props.filters.search || '');
const formType = ref<string>(props.filters.type || '');
const formSourceStoreId = ref<string>(
    props.filters.source_store_id !== null
        ? String(props.filters.source_store_id)
        : '',
);
const formDestinationStoreId = ref<string>(
    props.filters.destination_store_id !== null
        ? String(props.filters.destination_store_id)
        : '',
);
const formDateFrom = ref<string>(props.filters.date_from || '');
const formDateTo = ref<string>(props.filters.date_to || '');
const filtering = ref(false);
let filterTimer: ReturnType<typeof setTimeout> | null = null;

const totals = computed(() =>
    props.movements.reduce(
        (summary, movement) => ({
            items_count: summary.items_count + movement.items_count,
            total_value:
                summary.total_value +
                (movement.type === 'inventory_reconciliation'
                    ? movement.net_value
                    : movement.total_value),
        }),
        {
            items_count: 0,
            total_value: 0,
        },
    ),
);

function applyFilters(): void {
    const params: Record<string, string | number> = {};
    if (formSearch.value) {
        params.search = formSearch.value;
    }
    if (formType.value) {
        params.type = formType.value;
    }
    if (formSourceStoreId.value) {
        params.source_store_id = formSourceStoreId.value;
    }
    if (formDestinationStoreId.value) {
        params.destination_store_id = formDestinationStoreId.value;
    }
    if (formDateFrom.value) {
        params.date_from = formDateFrom.value;
    }
    if (formDateTo.value) {
        params.date_to = formDateTo.value;
    }
    router.get(route('stock-movements.index'), params, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (filtering.value = true),
        onFinish: () => (filtering.value = false),
    });
}

watch(
    [
        formSearch,
        formType,
        formSourceStoreId,
        formDestinationStoreId,
        formDateFrom,
        formDateTo,
    ],
    () => {
        if (filterTimer !== null) {
            clearTimeout(filterTimer);
        }
        filterTimer = setTimeout(applyFilters, 300);
    },
);
</script>

<template>
    <AppLayout :title="t('stock_movements.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('stock_movements.title')"
                :subtitle="t('stock_movements.subtitle')"
            >
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Link :href="route('stock-movements.create')">
                            <Button>
                                <Plus :size="14" />
                                {{ t('stock_movements.create_new') }}
                            </Button>
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <Card padded>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <SearchFilter
                        id="stock_movement_search"
                        v-model="formSearch"
                        :label="t('stock_movements.filter.search')"
                        :placeholder="t('stock_movements.search_placeholder')"
                        :busy="filtering"
                        class="lg:flex-1"
                    />
                    <div class="flex flex-col gap-1 lg:w-40">
                        <Label for="movement_type_filter">
                            {{ t('stock_movements.filter.type') }}
                        </Label>
                        <Select
                            id="movement_type_filter"
                            v-model="formType"
                            :options="[
                                {
                                    value: '',
                                    label: t(
                                        'stock_movements.filter.all_types',
                                    ),
                                },
                                {
                                    value: 'incoming',
                                    label: t('stock_movements.types.incoming'),
                                },
                                {
                                    value: 'transfer',
                                    label: t('stock_movements.types.transfer'),
                                },
                                {
                                    value: 'consumption',
                                    label: t(
                                        'stock_movements.types.consumption',
                                    ),
                                },
                                {
                                    value: 'inventory_reconciliation',
                                    label: t(
                                        'stock_movements.types.inventory_reconciliation',
                                    ),
                                },
                                {
                                    value: 'adjustment',
                                    label: t(
                                        'stock_movements.types.adjustment',
                                    ),
                                },
                                {
                                    value: 'reversal',
                                    label: t('stock_movements.types.reversal'),
                                },
                            ]"
                        />
                    </div>
                    <div class="flex flex-col gap-1 lg:w-44">
                        <Label for="source_store_filter">
                            {{ t('stock_movements.filter.source') }}
                        </Label>
                        <Select
                            id="source_store_filter"
                            v-model="formSourceStoreId"
                            :options="[
                                {
                                    value: '',
                                    label: t(
                                        'stock_movements.filter.all_sources',
                                    ),
                                },
                                ...stores.map((store) => ({
                                    value: String(store.id),
                                    label: store.name,
                                })),
                            ]"
                        />
                    </div>
                    <div class="flex flex-col gap-1 lg:w-44">
                        <Label for="destination_store_filter">
                            {{ t('stock_movements.filter.destination') }}
                        </Label>
                        <Select
                            id="destination_store_filter"
                            v-model="formDestinationStoreId"
                            :options="[
                                {
                                    value: '',
                                    label: t(
                                        'stock_movements.filter.all_destinations',
                                    ),
                                },
                                ...stores.map((store) => ({
                                    value: String(store.id),
                                    label: store.name,
                                })),
                            ]"
                        />
                    </div>
                    <div class="flex flex-col gap-1 lg:w-40">
                        <Label for="date_from_filter">
                            {{ t('stock_movements.filter.date_from') }}
                        </Label>
                        <Input
                            id="date_from_filter"
                            v-model="formDateFrom"
                            type="date"
                        />
                    </div>
                    <div class="flex flex-col gap-1 lg:w-40">
                        <Label for="date_to_filter">
                            {{ t('stock_movements.filter.date_to') }}
                        </Label>
                        <Input
                            id="date_to_filter"
                            v-model="formDateTo"
                            type="date"
                        />
                    </div>
                </div>
            </Card>

            <section class="space-y-4">
                <EmptyState
                    v-if="movements.length === 0"
                    :title="t('stock_movements.empty.title')"
                    :description="t('stock_movements.empty.description')"
                >
                    <template #action>
                        <Link :href="route('stock-movements.create')">
                            <Button>
                                <Plus :size="14" />
                                {{ t('stock_movements.create_new') }}
                            </Button>
                        </Link>
                    </template>
                </EmptyState>
                <DataTable
                    v-else
                    :loading="filtering"
                    :loading-label="t('common.loading')"
                >
                    <thead>
                        <tr>
                            <th>
                                {{ t('stock_movements.columns.number') }}
                            </th>
                            <th>{{ t('stock_movements.columns.type') }}</th>
                            <th>
                                {{ t('stock_movements.columns.source') }}
                            </th>
                            <th>
                                {{ t('stock_movements.columns.destination') }}
                            </th>
                            <th class="text-right">
                                {{ t('stock_movements.columns.items_count') }}
                            </th>
                            <th class="text-right">
                                {{ t('stock_movements.columns.value') }}
                            </th>
                            <th>{{ t('stock_movements.columns.date') }}</th>
                            <th>
                                {{ t('stock_movements.columns.created_by') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="movement in movements" :key="movement.id">
                            <td>
                                <Link
                                    :href="
                                        route(
                                            'stock-movements.show',
                                            movement.id,
                                        )
                                    "
                                    class="font-mono text-xs font-semibold text-on-surface hover:text-primary"
                                >
                                    {{ movement.number }}
                                </Link>
                            </td>
                            <td>
                                <MovementTypeBadge
                                    :type="movement.type"
                                    :label-key="movement.display_label_key"
                                />
                            </td>
                            <td class="text-xs text-on-surface-variant">
                                {{ movement.source_store_name ?? '—' }}
                            </td>
                            <td class="text-xs text-on-surface-variant">
                                {{ movement.store_name ?? '—' }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ movement.items_count }}
                            </td>
                            <td class="text-right text-on-surface-variant">
                                {{
                                    movement.type === 'inventory_reconciliation'
                                        ? formatSignedMoney(movement.net_value)
                                        : formatMoney(movement.total_value)
                                }}
                            </td>
                            <td class="text-xs text-on-surface-variant">
                                {{ formatDate(movement.created_at) }}
                            </td>
                            <td class="text-xs text-on-surface-variant">
                                {{ movement.created_by ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th
                                colspan="4"
                                data-label=""
                                data-mobile-hidden
                                class="text-left text-xs font-semibold text-on-surface-variant"
                            >
                                Σ
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface-variant"
                            >
                                {{ totals.items_count }}
                            </th>
                            <th
                                class="text-right text-xs font-semibold text-on-surface"
                            >
                                {{ formatMoney(totals.total_value) }}
                            </th>
                            <th
                                colspan="2"
                                data-label=""
                                data-mobile-hidden
                            ></th>
                        </tr>
                    </tfoot>
                </DataTable>

                <Pagination
                    v-if="movements.length > 0"
                    :current-page="pagination.current_page"
                    :last-page="pagination.last_page"
                    :total="pagination.total"
                    :per-page="pagination.per_page"
                    :base-url="route('stock-movements.index')"
                    :query-params="{
                        search: filters.search,
                        type: filters.type ?? undefined,
                        source_store_id: filters.source_store_id ?? undefined,
                        destination_store_id:
                            filters.destination_store_id ?? undefined,
                        date_from: filters.date_from ?? undefined,
                        date_to: filters.date_to ?? undefined,
                    }"
                    class="mt-4"
                />
            </section>
        </div>
    </AppLayout>
</template>
