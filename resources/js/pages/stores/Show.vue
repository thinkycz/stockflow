<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Package,
    Pencil,
    Store as StoreIcon,
    Trash2,
    History,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import MovementTypeBadge from '@/components/ui/MovementTypeBadge.vue';
import Sparkline from '@/components/ui/Sparkline.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatCzechDateTime } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';
import { formatDateTime, formatMoney, formatNumber } from '@/lib/format';

type MovementRow = {
    id: number;
    number: string;
    type: 'incoming' | 'outgoing' | 'adjustment';
    note: string | null;
    total_quantity: number;
    total_value: number;
    created_at: string;
    created_by: string | null;
    items: Array<{
        item_id: number;
        item_title: string;
        item_sku: string | null;
        quantity: number | null;
        total: number;
    }>;
};

type ItemSummary = {
    item_id: number;
    item_title: string;
    item_sku: string | null;
    movements_count: number;
    total_quantity: number;
    total_value: number;
};

type SparklinePoint = {
    label: string;
    value: number | null;
};

type InventoryStatus = 'in_stock' | 'low_stock' | 'out_of_stock';

type InventoryRow = {
    item_id: number;
    item_title: string;
    item_sku: string | null;
    quantity: number;
    unit: string | null;
    purchase_price: number;
    total_value: number;
    status: InventoryStatus;
    sparkline: SparklinePoint[];
    last_count_at: string | null;
    avg_daily_consumption: number;
    days_until_restock: number | null;
};

defineProps<{
    store: {
        id: number;
        name: string;
        address: string | null;
        status: 'active' | 'inactive';
        is_warehouse?: boolean;
        notes: string | null;
    };
    inventory: InventoryRow[];
    metrics: {
        total_outgoing_movements: number;
        total_outgoing_value: number;
        total_received_quantity: number;
        total_received_value: number;
    };
    movements: MovementRow[];
    items_received: ItemSummary[];
    now: string;
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

function destroyStore(id: number): void {
    if (!window.confirm(t('stores.confirm_delete'))) {
        return;
    }
    router.delete(route('stores.destroy', id));
}
</script>

<template>
    <AppLayout :title="store.name">
        <Head :title="store.name" />

        <div class="flex flex-col gap-6">
            <div>
                <Link
                    :href="route('stores.index')"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-primary"
                >
                    <ArrowLeft :size="12" />
                    {{ t('stores.back_to_list') }}
                </Link>
            </div>

            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-container text-on-surface-variant"
                    >
                        <StoreIcon :size="22" />
                    </div>
                    <div>
                        <h1
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ store.name }}
                        </h1>
                        <div
                            class="mt-2 flex items-center gap-2 text-xs text-on-surface-variant"
                        >
                            <span v-if="store.address">{{
                                store.address
                            }}</span>
                            <span v-if="store.address">·</span>
                            <Badge
                                :variant="
                                    store.status === 'active'
                                        ? 'success'
                                        : 'neutral'
                                "
                            >
                                {{
                                    store.status === 'active'
                                        ? t('stores.status.active')
                                        : t('stores.status.inactive')
                                }}
                            </Badge>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('stores.edit', store.id)">
                        <Button>
                            <Pencil :size="14" />
                            {{ t('common.edit') }}
                        </Button>
                    </Link>
                    <Button
                        variant="danger"
                        type="button"
                        @click="destroyStore(store.id)"
                    >
                        <Trash2 :size="14" />
                        {{ t('common.delete') }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('stores.metrics.outgoing_movements')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ metrics.total_outgoing_movements }}
                        </p>
                    </CardContent>
                </Card>
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('stores.metrics.received_quantity')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ metrics.total_received_quantity }}
                        </p>
                    </CardContent>
                </Card>
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('stores.metrics.received_value')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ formatMoney(metrics.total_received_value) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card padded>
                <CardHeader>
                    <CardTitle>{{ t('stores.inventory') }}</CardTitle>
                    <CardDescription>{{
                        t('stores.inventory_subtitle')
                    }}</CardDescription>
                </CardHeader>
                <EmptyState
                    v-if="inventory.length === 0"
                    :title="t('stores.inventory_empty')"
                />
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th>{{ t('stores.columns.item') }}</th>
                                <th>{{ t('stores.columns.sku') }}</th>
                                <th class="text-right">
                                    {{ t('stores.columns.total_quantity') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stores.columns.total_value') }}
                                </th>
                                <th>{{ t('stores.columns.status') }}</th>
                                <th>{{ t('stores.columns.sparkline') }}</th>
                                <th>{{ t('stores.columns.last_count') }}</th>
                                <th class="text-right">
                                    {{
                                        t(
                                            'stores.columns.avg_daily_consumption',
                                        )
                                    }}
                                </th>
                                <th class="text-right">
                                    {{ t('stores.columns.days_until_restock') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in inventory" :key="row.item_id">
                                <td>{{ row.item_title }}</td>
                                <td class="font-mono text-xs">
                                    {{ row.item_sku ?? '—' }}
                                </td>
                                <td class="text-right font-semibold">
                                    {{ row.quantity }}
                                </td>
                                <td class="text-right">
                                    {{ formatMoney(row.total_value) }}
                                </td>
                                <td>
                                    <StatusBadge :status="row.status" />
                                </td>
                                <td>
                                    <Sparkline
                                        :data="row.sparkline"
                                        :width="120"
                                        :height="32"
                                    />
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ formatCzechDateTime(row.last_count_at) }}
                                </td>
                                <td
                                    class="text-right text-xs text-on-surface-variant"
                                >
                                    {{
                                        row.avg_daily_consumption > 0
                                            ? formatNumber(
                                                  row.avg_daily_consumption,
                                                  2,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="text-right text-xs text-on-surface-variant"
                                >
                                    {{
                                        row.days_until_restock !== null
                                            ? `${row.days_until_restock} d`
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>

            <Card padded>
                <CardHeader>
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <Package
                                :size="14"
                                class="text-on-surface-variant"
                            />
                            {{ t('stores.items_received') }}
                        </span>
                    </CardTitle>
                    <CardDescription>
                        {{ t('stores.items_received_subtitle') }}
                    </CardDescription>
                </CardHeader>
                <EmptyState
                    v-if="items_received.length === 0"
                    :title="t('stores.items_empty.title')"
                    :description="t('stores.items_empty.description')"
                />
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th>{{ t('stores.columns.item') }}</th>
                                <th>{{ t('stores.columns.sku') }}</th>
                                <th class="text-right">
                                    {{ t('stores.columns.movements') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stores.columns.total_quantity') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stores.columns.total_value') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in items_received"
                                :key="row.item_id"
                            >
                                <td class="font-semibold text-on-surface">
                                    {{ row.item_title }}
                                </td>
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ row.item_sku ?? '—' }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ row.movements_count }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ row.total_quantity }}
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{ formatMoney(row.total_value) }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>

            <Card padded>
                <CardHeader>
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <History
                                :size="14"
                                class="text-on-surface-variant"
                            />
                            {{ t('stores.movement_history') }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <EmptyState
                    v-if="movements.length === 0"
                    :title="t('stores.movements_empty.title')"
                    :description="t('stores.movements_empty.description')"
                />
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th>
                                    {{ t('stock_movements.columns.number') }}
                                </th>
                                <th>{{ t('stock_movements.columns.type') }}</th>
                                <th class="text-right">
                                    {{ t('stock_movements.columns.quantity') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stock_movements.columns.value') }}
                                </th>
                                <th>{{ t('stock_movements.columns.date') }}</th>
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
                                    <MovementTypeBadge :type="movement.type" />
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ movement.total_quantity }}
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{ formatMoney(movement.total_value) }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ formatDateTime(movement.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
