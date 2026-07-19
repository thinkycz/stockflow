<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Boxes, CircleDollarSign, TriangleAlert, Database } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Chart from '@/components/ui/Chart.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import MetricCard from '@/components/ui/MetricCard.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';

type ItemRow = {
    item_id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    current_quantity: number;
    consumed_quantity: number;
    consumed_value: number;
    avg_daily_consumption: number;
    coverage_days: number;
    days_until_stockout: number | null;
    projected_stockout_at: string | null;
    status: 'ok' | 'due_soon' | 'out' | 'no_data';
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    period_days: number;
    current_inventory: { sku_count: number; value: number };
    consumption: { value: number; affected_skus: number };
    flows: {
        receipts_value: number;
        receipts_count: number;
        transfer_in_value: number;
        transfer_in_count: number;
        transfer_out_value: number;
        transfer_out_count: number;
    };
    risk: { due_soon: number; out: number; no_data: number };
    data_quality: {
        last_inventory_at: string | null;
        average_coverage_days: number;
        covered_items: number;
    };
    classified_changes: Array<{
        classification: string;
        rows_count: number;
        value: number;
    }>;
    consumption_series: Array<{ label: string; value: number }>;
    items: ItemRow[];
    filters: { store_id: number | null; period_days: number };
}>();

const { t } = useI18n();
useBoundLocale();
const route = useRoute();
const period = ref(String(props.period_days));

function applyPeriod(): void {
    router.get(
        route('reports.statistics'),
        { period_days: Number(period.value || props.period_days) },
        { preserveState: true, preserveScroll: true },
    );
}

function quantityWithUnit(value: number, unit: string | null): string {
    return `${formatNumber(value)}${unit ? ` ${unit}` : ''}`;
}
</script>

<template>
    <AppLayout :title="t('reports.statistics.title')">
        <Head :title="t('reports.statistics.title')" />
        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('reports.statistics.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('reports.statistics.subtitle') }}
                    </p>
                </div>
                <form
                    class="flex items-end gap-2"
                    @submit.prevent="applyPeriod"
                >
                    <div class="space-y-1">
                        <label
                            for="statistics_period"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('reports.statistics.period') }}
                        </label>
                        <Input
                            id="statistics_period"
                            v-model="period"
                            type="number"
                            min="7"
                            max="365"
                        />
                    </div>
                    <button
                        class="h-10 rounded-xl bg-primary px-4 text-xs font-semibold text-white"
                        type="submit"
                    >
                        {{ t('reports.statistics.apply') }}
                    </button>
                </form>
            </header>

            <EmptyState
                v-if="!props.store"
                :title="t('reports.no_store.title')"
                :description="t('reports.no_store.description')"
            />

            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        :title="t('reports.statistics.current_inventory.title')"
                        :value="formatMoney(props.current_inventory.value)"
                    >
                        <template #icon><Boxes :size="14" /></template>
                    </MetricCard>
                    <MetricCard
                        :title="t('reports.statistics.consumption.title')"
                        :value="formatMoney(props.consumption.value)"
                    >
                        <template #icon
                            ><CircleDollarSign :size="14"
                        /></template>
                    </MetricCard>
                    <MetricCard
                        :title="t('reports.statistics.risk.title')"
                        :value="
                            formatNumber(props.risk.due_soon + props.risk.out)
                        "
                    >
                        <template #icon><TriangleAlert :size="14" /></template>
                    </MetricCard>
                    <MetricCard
                        :title="t('reports.statistics.coverage.title')"
                        :value="`${props.data_quality.average_coverage_days} d`"
                    >
                        <template #icon><Database :size="14" /></template>
                    </MetricCard>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <Card padded>
                        <CardHeader
                            ><CardTitle>{{
                                t('reports.statistics.flows.receipts')
                            }}</CardTitle></CardHeader
                        >
                        <p class="font-heading text-xl font-bold">
                            {{ formatMoney(props.flows.receipts_value) }}
                        </p>
                        <CardDescription>{{
                            formatNumber(props.flows.receipts_count)
                        }}</CardDescription>
                    </Card>
                    <Card padded>
                        <CardHeader
                            ><CardTitle>{{
                                t('reports.statistics.flows.transfer_in')
                            }}</CardTitle></CardHeader
                        >
                        <p class="font-heading text-xl font-bold">
                            {{ formatMoney(props.flows.transfer_in_value) }}
                        </p>
                        <CardDescription>{{
                            formatNumber(props.flows.transfer_in_count)
                        }}</CardDescription>
                    </Card>
                    <Card padded>
                        <CardHeader
                            ><CardTitle>{{
                                t('reports.statistics.flows.transfer_out')
                            }}</CardTitle></CardHeader
                        >
                        <p class="font-heading text-xl font-bold">
                            {{ formatMoney(props.flows.transfer_out_value) }}
                        </p>
                        <CardDescription>{{
                            formatNumber(props.flows.transfer_out_count)
                        }}</CardDescription>
                    </Card>
                </div>

                <Card padded>
                    <CardHeader>
                        <CardTitle>{{
                            t('reports.statistics.charts.consumption')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t('reports.statistics.coverage.last_inventory')
                            }}:
                            {{
                                props.data_quality.last_inventory_at
                                    ? formatDate(
                                          props.data_quality.last_inventory_at,
                                      )
                                    : '—'
                            }}
                        </CardDescription>
                    </CardHeader>
                    <Chart
                        type="bar"
                        :title="t('reports.statistics.charts.consumption')"
                        :data="props.consumption_series"
                        :empty-text="t('reports.statistics.empty')"
                    />
                </Card>

                <Card padded>
                    <CardHeader>
                        <CardTitle>{{
                            t('reports.statistics.items.title')
                        }}</CardTitle>
                        <CardDescription>{{
                            t('reports.statistics.items.subtitle')
                        }}</CardDescription>
                    </CardHeader>
                    <div class="overflow-x-auto">
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>
                                        {{ t('reports.statistics.items.item') }}
                                    </th>
                                    <th class="text-right">
                                        {{
                                            t(
                                                'reports.statistics.items.current',
                                            )
                                        }}
                                    </th>
                                    <th class="text-right">
                                        {{
                                            t(
                                                'reports.statistics.items.consumed',
                                            )
                                        }}
                                    </th>
                                    <th class="text-right">
                                        {{
                                            t(
                                                'reports.statistics.items.average',
                                            )
                                        }}
                                    </th>
                                    <th class="text-right">
                                        {{
                                            t(
                                                'reports.statistics.items.stockout',
                                            )
                                        }}
                                    </th>
                                    <th>
                                        {{
                                            t('reports.statistics.items.status')
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in props.items"
                                    :key="item.item_id"
                                >
                                    <td>
                                        <div class="font-semibold">
                                            {{ item.title }}
                                        </div>
                                        <div
                                            class="font-mono text-xs text-on-surface-variant"
                                        >
                                            {{ item.sku ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        {{
                                            quantityWithUnit(
                                                item.current_quantity,
                                                item.unit,
                                            )
                                        }}
                                    </td>
                                    <td class="text-right">
                                        {{
                                            quantityWithUnit(
                                                item.consumed_quantity,
                                                item.unit,
                                            )
                                        }}
                                    </td>
                                    <td class="text-right">
                                        {{
                                            item.avg_daily_consumption > 0
                                                ? quantityWithUnit(
                                                      item.avg_daily_consumption,
                                                      item.unit,
                                                  )
                                                : '—'
                                        }}
                                    </td>
                                    <td class="text-right">
                                        {{
                                            item.projected_stockout_at
                                                ? formatDate(
                                                      item.projected_stockout_at,
                                                  )
                                                : '—'
                                        }}
                                    </td>
                                    <td>
                                        <StatusBadge :status="item.status" />
                                    </td>
                                </tr>
                            </tbody>
                        </DataTable>
                    </div>
                </Card>

                <Card padded>
                    <CardHeader
                        ><CardTitle>{{
                            t('reports.statistics.classifications.title')
                        }}</CardTitle></CardHeader
                    >
                    <div class="overflow-x-auto">
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>{{ t('reports.reason') }}</th>
                                    <th class="text-right">
                                        {{ t('reports.movements') }}
                                    </th>
                                    <th class="text-right">
                                        {{ t('reports.value') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in props.classified_changes"
                                    :key="row.classification"
                                >
                                    <td>
                                        {{
                                            t(
                                                `stock_movements.reasons.${row.classification}`,
                                            )
                                        }}
                                    </td>
                                    <td class="text-right">
                                        {{ formatNumber(row.rows_count) }}
                                    </td>
                                    <td class="text-right">
                                        {{ formatMoney(row.value) }}
                                    </td>
                                </tr>
                            </tbody>
                        </DataTable>
                    </div>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
