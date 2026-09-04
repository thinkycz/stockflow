<script setup lang="ts">
import {
    Boxes,
    CircleDollarSign,
    Database,
    Receipt,
    TrendingUp,
    TriangleAlert,
} from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Chart from '@/components/ui/Chart.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FilterField from '@/components/ui/FilterField.vue';
import MetricCard from '@/components/ui/MetricCard.vue';
import MonthPicker from '@/components/ui/MonthPicker.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import Tabs from '@/components/ui/Tabs.vue';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import {
    useFinancialReports,
    type FinancialReportsProps,
} from '@/features/finance/useFinancialReports';

const props = defineProps<FinancialReportsProps>();
const {
    t,
    activeTab,
    reportTabs,
    monthValue,
    channelData,
    selectMonth,
    quantityWithUnit,
} = useFinancialReports(props);
</script>

<template>
    <AppLayout :title="t('reports.title')">
        <div class="flex flex-col gap-6">
            <PageHeader
                :title="t('reports.title')"
                :subtitle="t('reports.unified_subtitle')"
            >
                <template #context>
                    <StoreContextIndicator />
                </template>
                <template #actions>
                    <FilterField
                        for="report_month_filter"
                        :label="t('reports.statements.month')"
                        class="min-w-52"
                    >
                        <MonthPicker
                            id="report_month_filter"
                            :model-value="monthValue"
                            :aria-label="t('reports.statements.month')"
                            @change="selectMonth"
                        />
                    </FilterField>
                </template>
            </PageHeader>

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

                <Tabs
                    :model-value="activeTab"
                    :items="reportTabs"
                    :label="t('reports.tabs.label')"
                    variant="underline"
                    @update:model-value="
                        activeTab = $event as 'finance' | 'inventory'
                    "
                />

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
