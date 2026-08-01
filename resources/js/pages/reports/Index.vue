<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Boxes,
    CircleDollarSign,
    Database,
    Receipt,
    TrendingUp,
    TriangleAlert,
} from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Chart from '@/components/ui/Chart.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import MetricCard from '@/components/ui/MetricCard.vue';
import Select from '@/components/ui/Select.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import {
    formatDate,
    formatMoney,
    formatMonth,
    formatNumber,
} from '@/lib/format';

type FinancialReport = {
    totals: {
        total_revenue: number;
        investment: number;
        card_provision: number;
        marketplace_provision: number;
        provisions: number;
        gross_margin: number;
        margin_percent: number;
        daily_average: number;
    };
    channels: Record<
        'cash' | 'card' | 'wolt' | 'bolt' | 'bolt_cash' | 'foodora',
        number
    >;
    daily: Array<{ label: string; value: number }>;
    days_with_revenue: number;
};

type InventoryItem = {
    item_id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    current_quantity: number;
    consumed_quantity: number;
    avg_daily_consumption: number;
    projected_stockout_at: string | null;
    status: 'ok' | 'due_soon' | 'out' | 'no_data';
};

type InventoryReport = {
    as_of: string;
    current_inventory: {
        sku_count: number;
        value: number;
        value_is_estimate: boolean;
    };
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
    items: InventoryItem[];
};

const props = defineProps<{
    active_store: { id: number; name: string } | null;
    filter: { store_id: number | null; year: number; month: number };
    summary: {
        total_revenue: number;
        gross_margin: number;
        consumption_cost: number;
        inventory_value: number;
    };
    financial_report: FinancialReport;
    inventory_report: InventoryReport;
}>();

const { t, locale } = useI18n();
useBoundLocale();
const route = useRoute();
const activeTab = ref<'finance' | 'inventory'>('finance');
const financeTab = ref<HTMLButtonElement | null>(null);
const inventoryTab = ref<HTMLButtonElement | null>(null);

const monthValue = computed(
    () => `${props.filter.year}-${String(props.filter.month).padStart(2, '0')}`,
);
const months = computed(() => {
    const now = new Date();

    return Array.from({ length: 12 }, (_, offset) => {
        const date = new Date(now.getFullYear(), now.getMonth() - offset, 1);
        const year = date.getFullYear();
        const month = date.getMonth() + 1;

        return {
            value: `${year}-${String(month).padStart(2, '0')}`,
            label: formatMonth(year, month, locale.value),
        };
    });
});
const channelData = computed(() => [
    {
        key: 'cash',
        label: t('statements.columns.cash'),
        value: props.financial_report.channels.cash,
        color: '#16a34a',
    },
    {
        key: 'card',
        label: t('statements.columns.card'),
        value: props.financial_report.channels.card,
        color: '#1f6feb',
    },
    {
        key: 'wolt',
        label: t('statements.columns.wolt'),
        value: props.financial_report.channels.wolt,
        color: '#f59e0b',
    },
    {
        key: 'bolt',
        label: t('statements.columns.bolt'),
        value: props.financial_report.channels.bolt,
        color: '#7c3aed',
    },
    {
        key: 'bolt_cash',
        label: t('statements.columns.bolt_cash'),
        value: props.financial_report.channels.bolt_cash,
        color: '#db2777',
    },
    {
        key: 'foodora',
        label: t('statements.columns.foodora'),
        value: props.financial_report.channels.foodora,
        color: '#0891b2',
    },
]);

function selectMonth(value: string | number | null | undefined): void {
    const [year, month] = String(value ?? '')
        .split('-')
        .map(Number);
    if (!year || !month) return;
    router.get(
        route('reports.index'),
        { store_id: props.filter.store_id, year, month },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function selectTab(tab: 'finance' | 'inventory'): void {
    activeTab.value = tab;
    nextTick(() =>
        (tab === 'finance' ? financeTab.value : inventoryTab.value)?.focus(),
    );
}

function quantityWithUnit(value: number, unit: string | null): string {
    return `${formatNumber(value)}${unit ? ` ${unit}` : ''}`;
}
</script>

<template>
    <AppLayout :title="t('reports.title')">
        <Head :title="t('reports.title')" />
        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('reports.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('reports.unified_subtitle') }}
                    </p>
                    <StoreContextIndicator />
                </div>
                <div class="min-w-52 space-y-1">
                    <label
                        for="report_month_filter"
                        class="text-xs font-semibold text-on-surface-variant"
                    >
                        {{ t('reports.statements.month') }}
                    </label>
                    <Select
                        id="report_month_filter"
                        :model-value="monthValue"
                        :options="months"
                        @update:model-value="selectMonth"
                    />
                </div>
            </header>

            <EmptyState
                v-if="!props.active_store"
                :title="t('reports.no_store.title')"
                :description="t('reports.no_store.description')"
            />

            <template v-else>
                <section aria-labelledby="report-summary-title">
                    <h2 id="report-summary-title" class="sr-only">
                        {{ t('reports.summary.title') }}
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <MetricCard
                            :title="t('reports.summary.revenue')"
                            :value="formatMoney(props.summary.total_revenue)"
                        >
                            <template #icon><Receipt :size="14" /></template>
                        </MetricCard>
                        <MetricCard
                            :title="t('reports.summary.margin')"
                            :value="formatMoney(props.summary.gross_margin)"
                        >
                            <template #icon><TrendingUp :size="14" /></template>
                        </MetricCard>
                        <MetricCard
                            :title="t('reports.summary.consumption')"
                            :value="formatMoney(props.summary.consumption_cost)"
                        >
                            <template #icon
                                ><CircleDollarSign :size="14"
                            /></template>
                        </MetricCard>
                        <MetricCard
                            :title="t('reports.summary.inventory')"
                            :value="formatMoney(props.summary.inventory_value)"
                        >
                            <template #icon><Boxes :size="14" /></template>
                        </MetricCard>
                    </div>
                    <p
                        v-if="
                            props.inventory_report.current_inventory
                                .value_is_estimate
                        "
                        class="mt-2 text-xs text-on-surface-variant"
                    >
                        {{ t('reports.summary.inventory_estimate') }}
                    </p>
                </section>

                <div
                    class="border-b border-outline-glass"
                    role="tablist"
                    :aria-label="t('reports.tabs.label')"
                >
                    <button
                        id="reports-tab-finance"
                        ref="financeTab"
                        type="button"
                        role="tab"
                        aria-controls="reports-panel-finance"
                        :aria-selected="activeTab === 'finance'"
                        :tabindex="activeTab === 'finance' ? 0 : -1"
                        class="border-b-2 px-4 py-3 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        :class="
                            activeTab === 'finance'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-on-surface-variant'
                        "
                        @click="selectTab('finance')"
                        @keydown.right.prevent="selectTab('inventory')"
                    >
                        {{ t('reports.tabs.finance') }}
                    </button>
                    <button
                        id="reports-tab-inventory"
                        ref="inventoryTab"
                        type="button"
                        role="tab"
                        aria-controls="reports-panel-inventory"
                        :aria-selected="activeTab === 'inventory'"
                        :tabindex="activeTab === 'inventory' ? 0 : -1"
                        class="border-b-2 px-4 py-3 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        :class="
                            activeTab === 'inventory'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-on-surface-variant'
                        "
                        @click="selectTab('inventory')"
                        @keydown.left.prevent="selectTab('finance')"
                    >
                        {{ t('reports.tabs.inventory') }}
                    </button>
                </div>

                <section
                    v-show="activeTab === 'finance'"
                    id="reports-panel-finance"
                    role="tabpanel"
                    aria-labelledby="reports-tab-finance"
                    tabindex="0"
                    class="space-y-4"
                >
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <MetricCard
                            :title="t('reports.statements.provisions')"
                            :value="
                                formatMoney(
                                    props.financial_report.totals.provisions,
                                )
                            "
                        />
                        <MetricCard
                            :title="t('reports.statements.margin_percent')"
                            :value="`${props.financial_report.totals.margin_percent} %`"
                        />
                        <MetricCard
                            :title="t('reports.statements.daily_average')"
                            :value="
                                formatMoney(
                                    props.financial_report.totals.daily_average,
                                )
                            "
                        />
                        <MetricCard
                            :title="t('reports.statements.cash_share')"
                            :value="`${props.financial_report.totals.total_revenue > 0 ? ((props.financial_report.channels.cash / props.financial_report.totals.total_revenue) * 100).toFixed(1) : '0.0'} %`"
                        />
                    </div>
                    <Chart
                        type="line"
                        :title="t('reports.statements.daily_revenue')"
                        :data="props.financial_report.daily"
                        :empty-text="t('reports.statements.empty')"
                    />
                    <div class="grid gap-4 lg:grid-cols-2">
                        <Chart
                            type="pie"
                            :title="t('reports.statements.channel_pie')"
                            :data="channelData"
                            :series="channelData"
                            :empty-text="t('reports.statements.empty')"
                        />
                        <Chart
                            type="bar"
                            :title="t('reports.statements.channel_bars')"
                            :data="channelData"
                            :series="channelData"
                            :empty-text="t('reports.statements.empty')"
                        />
                    </div>
                </section>

                <section
                    v-show="activeTab === 'inventory'"
                    id="reports-panel-inventory"
                    role="tabpanel"
                    aria-labelledby="reports-tab-inventory"
                    tabindex="0"
                    class="space-y-4"
                >
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <MetricCard
                            :title="t('reports.statistics.risk.title')"
                            :value="
                                formatNumber(
                                    props.inventory_report.risk.due_soon +
                                        props.inventory_report.risk.out,
                                )
                            "
                        >
                            <template #icon
                                ><TriangleAlert :size="14"
                            /></template>
                        </MetricCard>
                        <MetricCard
                            :title="t('reports.statistics.coverage.title')"
                            :value="`${props.inventory_report.data_quality.average_coverage_days} d`"
                        >
                            <template #icon><Database :size="14" /></template>
                        </MetricCard>
                        <MetricCard
                            :title="
                                t('reports.statistics.current_inventory.items')
                            "
                            :value="
                                formatNumber(
                                    props.inventory_report.current_inventory
                                        .sku_count,
                                )
                            "
                        />
                        <MetricCard
                            :title="t('reports.statistics.consumption.title')"
                            :value="
                                formatMoney(
                                    props.inventory_report.consumption.value,
                                )
                            "
                        />
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card padded
                            ><CardHeader
                                ><CardTitle>{{
                                    t('reports.statistics.flows.receipts')
                                }}</CardTitle></CardHeader
                            >
                            <p class="font-heading text-xl font-bold">
                                {{
                                    formatMoney(
                                        props.inventory_report.flows
                                            .receipts_value,
                                    )
                                }}
                            </p>
                            <CardDescription>{{
                                formatNumber(
                                    props.inventory_report.flows.receipts_count,
                                )
                            }}</CardDescription></Card
                        >
                        <Card padded
                            ><CardHeader
                                ><CardTitle>{{
                                    t('reports.statistics.flows.transfer_in')
                                }}</CardTitle></CardHeader
                            >
                            <p class="font-heading text-xl font-bold">
                                {{
                                    formatMoney(
                                        props.inventory_report.flows
                                            .transfer_in_value,
                                    )
                                }}
                            </p>
                            <CardDescription>{{
                                formatNumber(
                                    props.inventory_report.flows
                                        .transfer_in_count,
                                )
                            }}</CardDescription></Card
                        >
                        <Card padded
                            ><CardHeader
                                ><CardTitle>{{
                                    t('reports.statistics.flows.transfer_out')
                                }}</CardTitle></CardHeader
                            >
                            <p class="font-heading text-xl font-bold">
                                {{
                                    formatMoney(
                                        props.inventory_report.flows
                                            .transfer_out_value,
                                    )
                                }}
                            </p>
                            <CardDescription>{{
                                formatNumber(
                                    props.inventory_report.flows
                                        .transfer_out_count,
                                )
                            }}</CardDescription></Card
                        >
                    </div>
                    <Card padded>
                        <CardHeader>
                            <CardTitle>{{
                                t('reports.statistics.charts.consumption')
                            }}</CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'reports.statistics.coverage.last_inventory',
                                    )
                                }}:
                                {{
                                    props.inventory_report.data_quality
                                        .last_inventory_at
                                        ? formatDate(
                                              props.inventory_report
                                                  .data_quality
                                                  .last_inventory_at,
                                          )
                                        : '—'
                                }}
                            </CardDescription>
                        </CardHeader>
                        <Chart
                            type="bar"
                            :title="t('reports.statistics.charts.consumption')"
                            :data="props.inventory_report.consumption_series"
                            :empty-text="t('reports.statistics.empty')"
                        />
                    </Card>
                    <section class="space-y-4">
                        <CardHeader
                            ><CardTitle>{{
                                t('reports.statistics.items.title')
                            }}</CardTitle
                            ><CardDescription>{{
                                t('reports.statistics.items.subtitle')
                            }}</CardDescription></CardHeader
                        >
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
                                    v-for="item in props.inventory_report.items"
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
                    </section>
                    <section class="space-y-4">
                        <CardHeader
                            ><CardTitle>{{
                                t('reports.statistics.classifications.title')
                            }}</CardTitle></CardHeader
                        >
                        <DataTable
                            ><thead>
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
                                    v-for="row in props.inventory_report
                                        .classified_changes"
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
                    </section>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
