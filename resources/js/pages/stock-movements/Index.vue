<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import MovementTypeBadge from '@/features/stock-movements/components/MovementTypeBadge.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import SearchFilter from '@/components/ui/SearchFilter.vue';
import { formatDate, formatMoney, formatSignedMoney } from '@/lib/format';
import {
    useMovementList,
    type MovementListProps,
} from '@/features/stock-movements/useMovementList';

const props = defineProps<MovementListProps>();
const {
    t,
    route,
    formSearch,
    formType,
    formSourceStoreId,
    formDestinationStoreId,
    formDateFrom,
    formDateTo,
    filtering,
    totals,
} = useMovementList(props);
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
