<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Boxes, FileText, History } from '@lucide/vue';
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
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatDateTime, formatMoney, formatNumber } from '@/lib/format';

type MovementRow = {
    id: number;
    number: string;
    type: 'incoming' | 'outgoing' | 'adjustment';
    store_id: number | null;
    total_quantity: number;
    total_value: number;
    quantity: number | null;
    quantity_before: number | null;
    quantity_after: number | null;
    quantity_difference: number | null;
    adjustment_reason: string | null;
    created_at: string;
};

type StoreQuantityRow = {
    store_id: number;
    store_name: string;
    is_warehouse: boolean;
    quantity: number;
};

const props = defineProps<{
    item: {
        id: number;
        title: string;
        sku: string | null;
        unit: string | null;
        warehouse_quantity: number;
        total_quantity: number;
        purchase_price: number;
        total_value: number;
        description: string | null;
        status: 'in_stock' | 'low_stock' | 'out_of_stock';
        created_at: string;
    };
    store_quantities: StoreQuantityRow[];
    movements: MovementRow[];
}>();

const { t } = useI18n();

useBoundLocale();
</script>

<template>
    <AppLayout :title="item.title">
        <Head :title="item.title" />

        <div class="flex flex-col gap-6">
            <div>
                <Link
                    :href="route('items.index')"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-primary"
                >
                    <ArrowLeft :size="12" />
                    {{ t('items.back_to_inventory') }}
                </Link>
            </div>

            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-container text-on-surface-variant"
                    >
                        <Boxes :size="22" />
                    </div>
                    <div>
                        <h1
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ item.title }}
                        </h1>
                        <div
                            class="mt-2 flex items-center gap-2 text-xs text-on-surface-variant"
                        >
                            <Badge variant="neutral">
                                {{ item.sku ?? t('items.no_sku') }}
                            </Badge>
                            <span v-if="item.unit">·</span>
                            <span v-if="item.unit">{{ item.unit }}</span>
                            <span>·</span>
                            <StatusBadge :status="item.status" />
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="
                            route('stock-movements.create', {
                                type: 'adjustment',
                                item_id: item.id,
                            })
                        "
                    >
                        <Button variant="secondary">
                            <FileText :size="14" />
                            {{ t('items.adjust_stock') }}
                        </Button>
                    </Link>
                    <Link :href="route('items.edit', item.id)">
                        <Button>
                            <Pencil :size="14" />
                            {{ t('common.edit') }}
                        </Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('items.metrics.quantity')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ formatNumber(item.warehouse_quantity) }}
                        </p>
                        <p
                            v-if="item.unit"
                            class="mt-1 text-xs text-on-surface-variant"
                        >
                            {{ item.unit }}
                        </p>
                    </CardContent>
                </Card>
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('items.metrics.unit_price')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ formatMoney(item.purchase_price) }}
                        </p>
                    </CardContent>
                </Card>
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('items.metrics.total_value')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ formatMoney(item.total_value) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card padded>
                <CardHeader>
                    <CardTitle>{{ t('items.store_quantities') }}</CardTitle>
                    <CardDescription>{{
                        t('items.store_quantities_subtitle')
                    }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        v-if="store_quantities.length === 0"
                        :title="t('items.no_store_stock')"
                    />
                    <DataTable v-else>
                        <thead>
                            <tr>
                                <th>{{ t('stores.columns.name') }}</th>
                                <th class="text-right">
                                    {{ t('items.metrics.quantity') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in store_quantities"
                                :key="row.store_id"
                            >
                                <td>
                                    {{ row.store_name }}
                                    <Badge
                                        v-if="row.is_warehouse"
                                        variant="neutral"
                                        class="ml-2"
                                    >
                                        {{ t('stores.warehouse') }}
                                    </Badge>
                                </td>
                                <td class="text-right font-semibold">
                                    {{ formatNumber(row.quantity) }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </CardContent>
            </Card>

            <Card padded>
                <CardHeader>
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <History
                                :size="14"
                                class="text-on-surface-variant"
                            />
                            {{ t('items.movement_history') }}
                        </span>
                    </CardTitle>
                    <CardDescription>
                        {{ t('items.movement_history_subtitle') }}
                    </CardDescription>
                </CardHeader>
                <EmptyState
                    v-if="movements.length === 0"
                    :title="t('items.movements_empty.title')"
                    :description="t('items.movements_empty.description')"
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
                                    {{
                                        movement.type === 'adjustment'
                                            ? formatNumber(
                                                  movement.quantity_difference ??
                                                      0,
                                              )
                                            : formatNumber(
                                                  movement.quantity ?? 0,
                                              )
                                    }}
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
