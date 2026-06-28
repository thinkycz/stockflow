<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import MovementTypeBadge from '@/components/ui/MovementTypeBadge.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';

type MovementRow = {
    id: number;
    number: string;
    type: 'incoming' | 'outgoing' | 'adjustment';
    display_label_key: 'incoming' | 'outgoing' | 'transfer' | 'adjustment';
    store_id: number | null;
    store_name: string | null;
    source_store_id: number | null;
    source_store_name: string | null;
    created_at: string;
    total_quantity: number;
    total_value: number;
    items_count: number;
    created_by: string | null;
};

const props = defineProps<{
    movements: MovementRow[];
    filters: {
        search: string;
        type: string | null;
        store_id: number | null;
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
const formDateFrom = ref<string>(props.filters.date_from || '');
const formDateTo = ref<string>(props.filters.date_to || '');
let filterTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(): void {
    const params: Record<string, string | number> = {};
    if (formSearch.value) {
        params.search = formSearch.value;
    }
    if (formType.value) {
        params.type = formType.value;
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
    });
}

watch([formSearch, formType, formDateFrom, formDateTo], () => {
    if (filterTimer !== null) {
        clearTimeout(filterTimer);
    }
    filterTimer = setTimeout(applyFilters, 300);
});

function destroyMovement(id: number): void {
    if (!window.confirm(t('stock_movements.confirm_delete'))) {
        return;
    }
    router.delete(route('stock-movements.destroy', id));
}
</script>

<template>
    <AppLayout :title="t('stock_movements.title')">
        <Head :title="t('stock_movements.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('stock_movements.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('stock_movements.subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('stock-movements.create')">
                        <Button>
                            <Plus :size="14" />
                            {{ t('stock_movements.create_new') }}
                        </Button>
                    </Link>
                </div>
            </header>

            <Card padded>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="relative lg:flex-1">
                        <Search
                            :size="14"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-on-surface-variant"
                        />
                        <Input
                            v-model="formSearch"
                            type="search"
                            :placeholder="
                                t('stock_movements.search_placeholder')
                            "
                            class="pl-9"
                        />
                    </div>
                    <div class="lg:w-40">
                        <Select
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
                                    value: 'outgoing',
                                    label: t('stock_movements.types.outgoing'),
                                },
                                {
                                    value: 'adjustment',
                                    label: t(
                                        'stock_movements.types.adjustment',
                                    ),
                                },
                            ]"
                        />
                    </div>
                    <div class="lg:w-40">
                        <Input
                            v-model="formDateFrom"
                            type="date"
                            :aria-label="t('stock_movements.filter.date_from')"
                        />
                    </div>
                    <div class="lg:w-40">
                        <Input
                            v-model="formDateTo"
                            type="date"
                            :aria-label="t('stock_movements.filter.date_to')"
                        />
                    </div>
                </div>
            </Card>

            <Card padded>
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
                <div v-else class="overflow-x-auto">
                    <DataTable>
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
                                    {{
                                        t('stock_movements.columns.destination')
                                    }}
                                </th>
                                <th class="text-right">
                                    {{
                                        t('stock_movements.columns.items_count')
                                    }}
                                </th>
                                <th class="text-right">
                                    {{ t('stock_movements.columns.quantity') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stock_movements.columns.value') }}
                                </th>
                                <th>{{ t('stock_movements.columns.date') }}</th>
                                <th>
                                    {{
                                        t('stock_movements.columns.created_by')
                                    }}
                                </th>
                                <th class="w-0">
                                    {{ t('stock_movements.columns.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="movement in movements"
                                :key="movement.id"
                            >
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
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatNumber(movement.total_quantity) }}
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{ formatMoney(movement.total_value) }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ formatDate(movement.created_at) }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ movement.created_by ?? '—' }}
                                </td>
                                <td>
                                    <Button
                                        variant="ghost"
                                        type="button"
                                        :aria-label="t('common.delete')"
                                        @click="destroyMovement(movement.id)"
                                    >
                                        <Trash2 :size="14" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>

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
                        date_from: filters.date_from ?? undefined,
                        date_to: filters.date_to ?? undefined,
                    }"
                    class="mt-4"
                />
            </Card>
        </div>
    </AppLayout>
</template>
