<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Layers,
    TrendingUp,
    TrendingDown,
    Building2,
    Sliders,
    Boxes,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatMoney, formatNumber } from '@/lib/format';

type StoreConsumption = {
    store_id: number;
    store_name: string;
    movements_count: number;
    total_quantity: number;
    total_value: number;
};

type MostMoved = {
    item_id: number;
    item_title: string;
    item_sku: string | null;
    total_quantity: number;
    total_value: number;
    rows_count: number;
};

type AdjustmentSummary = {
    reason: string;
    rows_count: number;
    total_quantity: number;
};

defineProps<{
    inventory_value: number;
    monthly: {
        incoming: number;
        outgoing: number;
    };
    store_consumption: StoreConsumption[];
    most_moved: MostMoved[];
    adjustments: AdjustmentSummary[];
    reasons: string[];
}>();

const { t } = useI18n();

useBoundLocale();
</script>

<template>
    <AppLayout :title="t('reports.title')">
        <Head :title="t('reports.title')" />

        <div class="flex flex-col gap-6">
            <div>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('reports.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('reports.subtitle') }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('reports.inventory_value')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="flex items-center gap-2 font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            <Layers :size="18" class="text-primary" />
                            {{ formatMoney(inventory_value) }}
                        </p>
                    </CardContent>
                </Card>
                <Card padded>
                    <CardHeader>
                        <CardDescription>
                            {{ t('reports.monthly_incoming') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="flex items-center gap-2 font-heading text-2xl font-bold tracking-tight text-emerald-600"
                        >
                            <TrendingUp :size="18" />
                            {{ formatMoney(monthly.incoming) }}
                        </p>
                    </CardContent>
                </Card>
                <Card padded>
                    <CardHeader>
                        <CardDescription>
                            {{ t('reports.monthly_outgoing') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="flex items-center gap-2 font-heading text-2xl font-bold tracking-tight text-rose-600"
                        >
                            <TrendingDown :size="18" />
                            {{ formatMoney(monthly.outgoing) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card padded>
                <CardHeader>
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <Building2
                                :size="14"
                                class="text-on-surface-variant"
                            />
                            {{ t('reports.store_consumption') }}
                        </span>
                    </CardTitle>
                    <CardDescription>{{
                        t('reports.store_consumption_subtitle')
                    }}</CardDescription>
                </CardHeader>
                <EmptyState
                    v-if="store_consumption.length === 0"
                    :title="t('reports.empty.stores')"
                />
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th>{{ t('reports.store') }}</th>
                                <th class="text-right">
                                    {{ t('reports.movements') }}
                                </th>
                                <th class="text-right">
                                    {{ t('reports.quantity') }}
                                </th>
                                <th class="text-right">
                                    {{ t('reports.value') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in store_consumption"
                                :key="row.store_id"
                            >
                                <td class="font-semibold text-on-surface">
                                    {{ row.store_name }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ row.movements_count }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatNumber(row.total_quantity) }}
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
                            <Boxes :size="14" class="text-on-surface-variant" />
                            {{ t('reports.most_moved') }}
                        </span>
                    </CardTitle>
                    <CardDescription>{{
                        t('reports.most_moved_subtitle')
                    }}</CardDescription>
                </CardHeader>
                <EmptyState
                    v-if="most_moved.length === 0"
                    :title="t('reports.empty.movements')"
                />
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th>{{ t('reports.item') }}</th>
                                <th>{{ t('reports.sku') }}</th>
                                <th class="text-right">
                                    {{ t('reports.movements') }}
                                </th>
                                <th class="text-right">
                                    {{ t('reports.quantity') }}
                                </th>
                                <th class="text-right">
                                    {{ t('reports.value') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in most_moved" :key="row.item_id">
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
                                    {{ row.rows_count }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatNumber(row.total_quantity) }}
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
                            <Sliders
                                :size="14"
                                class="text-on-surface-variant"
                            />
                            {{ t('reports.adjustments_by_reason') }}
                        </span>
                    </CardTitle>
                    <CardDescription>{{
                        t('reports.adjustments_by_reason_subtitle')
                    }}</CardDescription>
                </CardHeader>
                <EmptyState
                    v-if="adjustments.length === 0"
                    :title="t('reports.empty.adjustments')"
                />
                <div v-else class="overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th>{{ t('reports.reason') }}</th>
                                <th class="text-right">
                                    {{ t('reports.adjustment_count') }}
                                </th>
                                <th class="text-right">
                                    {{ t('reports.quantity') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in adjustments" :key="row.reason">
                                <td class="font-semibold text-on-surface">
                                    {{
                                        row.reason
                                            ? t(
                                                  `stock_movements.reasons.${row.reason}`,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ row.rows_count }}
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{ formatNumber(row.total_quantity) }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
