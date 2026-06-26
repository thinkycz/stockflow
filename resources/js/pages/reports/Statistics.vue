<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Boxes,
    CircleDollarSign,
    ShoppingCart,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import Chart from '@/components/ui/Chart.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import MetricCard from '@/components/ui/MetricCard.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatMoney } from '@/lib/format';

type ChannelTotals = {
    cash: number;
    card: number;
    wolt: number;
    bolt: number;
    bolt_cash: number;
    foodora: number;
};

type SalesSummary = {
    total: number;
    count: number;
    channels: ChannelTotals;
    daily: Array<{ label: string; value: number }>;
};

type MovementSummary = {
    quantity: number;
    value: number;
    movements: number;
};

type InventorySummary = {
    items: number;
    value: number;
};

type TopConsumedItem = {
    item_id: number;
    title: string;
    sku: string | null;
    total_quantity: number;
    total_value: number;
    rows_count: number;
};

type DailyPoint = {
    label: string;
    incoming: number;
    outgoing: number;
};

const props = defineProps<{
    store: { id: number; name: string } | null;
    period_days: number;
    sales: SalesSummary;
    incoming: MovementSummary;
    outgoing: MovementSummary;
    current_inventory: InventorySummary;
    top_consumed: TopConsumedItem[];
    daily_series: DailyPoint[];
    filters: { store_id: number | null; period_days: number };
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();

const incomingSeries = computed(() =>
    props.daily_series.map((point) => ({
        label: point.label,
        value: point.incoming,
    })),
);

const outgoingSeries = computed(() =>
    props.daily_series.map((point) => ({
        label: point.label,
        value: point.outgoing,
    })),
);

const channelSlices = computed(() => {
    const channels = props.sales.channels;
    return [
        { label: t('statements.columns.cash'), value: channels.cash },
        { label: t('statements.columns.card'), value: channels.card },
        { label: t('statements.columns.wolt'), value: channels.wolt },
        { label: t('statements.columns.bolt'), value: channels.bolt },
        { label: t('statements.columns.bolt_cash'), value: channels.bolt_cash },
        { label: t('statements.columns.foodora'), value: channels.foodora },
    ];
});

const periodValue = computed(() => String(props.filters.period_days));

function applyPeriod(event: Event): void {
    const target = event.target as HTMLInputElement;
    const period = Math.max(7, Math.min(365, Number(target.value) || 30));
    router.get(
        route('reports.statistics'),
        { store_id: props.filters.store_id, period_days: period },
        { preserveState: true, preserveScroll: true },
    );
}

function formatCount(value: number): string {
    return value.toLocaleString('cs-CZ', { maximumFractionDigits: 0 });
}
</script>

<template>
    <AppLayout :title="t('reports.statistics.title')">
        <Head :title="t('reports.statistics.title')" />

        <div class="flex flex-col gap-6">
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

            <Card padded>
                <div class="grid gap-4 lg:max-w-md">
                    <div class="space-y-2">
                        <label
                            for="statistics_period"
                            class="text-xs font-semibold text-on-surface-variant"
                        >
                            {{ t('reports.statistics.period') }}
                        </label>
                        <Input
                            id="statistics_period"
                            type="number"
                            min="7"
                            max="365"
                            step="1"
                            :model-value="periodValue"
                            @change="applyPeriod"
                        />
                    </div>
                </div>
            </Card>

            <EmptyState
                v-if="!props.store"
                :title="t('reports.no_store.title')"
                :description="t('reports.no_store.description')"
            />

            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        :title="t('reports.statistics.sales.title')"
                        :value="formatMoney(props.sales.total)"
                    >
                        <template #icon>
                            <CircleDollarSign :size="14" />
                        </template>
                    </MetricCard>
                    <MetricCard
                        :title="t('reports.statistics.incoming.title')"
                        :value="formatMoney(props.incoming.value)"
                    >
                        <template #icon>
                            <ArrowDownToLine :size="14" />
                        </template>
                    </MetricCard>
                    <MetricCard
                        :title="t('reports.statistics.outgoing.title')"
                        :value="formatMoney(props.outgoing.value)"
                    >
                        <template #icon>
                            <ArrowUpFromLine :size="14" />
                        </template>
                    </MetricCard>
                    <MetricCard
                        :title="t('reports.statistics.current_inventory.title')"
                        :value="formatMoney(props.current_inventory.value)"
                    >
                        <template #icon>
                            <Boxes :size="14" />
                        </template>
                    </MetricCard>
                </div>

                <Card padded>
                    <CardHeader>
                        <CardTitle>{{
                            t('reports.statistics.charts.daily')
                        }}</CardTitle>
                        <CardDescription>
                            {{ props.store.name }} ·
                            {{ formatCount(props.incoming.movements) }} /
                            {{ formatCount(props.outgoing.movements) }}
                        </CardDescription>
                    </CardHeader>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <Chart
                            type="bar"
                            :title="t('reports.statistics.charts.incoming')"
                            :data="incomingSeries"
                            :height="220"
                            :empty-text="
                                t('reports.statistics.charts.incoming')
                            "
                        />
                        <Chart
                            type="bar"
                            :title="t('reports.statistics.charts.outgoing')"
                            :data="outgoingSeries"
                            :height="220"
                            :empty-text="
                                t('reports.statistics.charts.outgoing')
                            "
                        />
                    </div>
                </Card>

                <Card padded>
                    <CardHeader>
                        <CardTitle>
                            {{ t('reports.statistics.top_consumed.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('reports.statistics.top_consumed.subtitle') }}
                        </CardDescription>
                    </CardHeader>
                    <div class="overflow-x-auto">
                        <DataTable class="[&_td]:px-2 [&_th]:px-2">
                            <thead>
                                <tr>
                                    <th class="min-w-[12rem] text-left">
                                        {{
                                            t(
                                                'reports.statistics.top_consumed.item',
                                            )
                                        }}
                                    </th>
                                    <th class="min-w-[8rem] text-left">
                                        {{
                                            t(
                                                'reports.statistics.top_consumed.sku',
                                            )
                                        }}
                                    </th>
                                    <th class="min-w-[8rem] text-right">
                                        {{
                                            t(
                                                'reports.statistics.top_consumed.quantity',
                                            )
                                        }}
                                    </th>
                                    <th class="min-w-[8rem] text-right">
                                        {{
                                            t(
                                                'reports.statistics.top_consumed.value',
                                            )
                                        }}
                                    </th>
                                    <th class="min-w-[6rem] text-right">
                                        {{
                                            t(
                                                'reports.statistics.top_consumed.rows',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="props.top_consumed.length === 0">
                                    <td
                                        colspan="5"
                                        class="py-6 text-center text-xs text-on-surface-variant"
                                    >
                                        —
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in props.top_consumed"
                                    :key="item.item_id"
                                >
                                    <td class="font-semibold text-on-surface">
                                        {{ item.title }}
                                    </td>
                                    <td
                                        class="font-mono text-xs text-on-surface-variant"
                                    >
                                        {{ item.sku ?? '–' }}
                                    </td>
                                    <td
                                        class="text-right text-xs text-on-surface-variant"
                                    >
                                        {{ formatCount(item.total_quantity) }}
                                    </td>
                                    <td
                                        class="text-right text-xs font-semibold text-on-surface"
                                    >
                                        {{ formatMoney(item.total_value) }}
                                    </td>
                                    <td
                                        class="text-right text-xs text-on-surface-variant"
                                    >
                                        {{ formatCount(item.rows_count) }}
                                    </td>
                                </tr>
                            </tbody>
                        </DataTable>
                    </div>
                </Card>

                <Card padded>
                    <CardHeader>
                        <CardTitle>{{
                            t('reports.statistics.sales.title')
                        }}</CardTitle>
                        <CardDescription>
                            <ShoppingCart :size="12" class="inline-block" />
                            {{ formatCount(props.sales.count) }}
                            {{ t('reports.statistics.sales.count') }}
                        </CardDescription>
                    </CardHeader>
                    <Chart
                        type="pie"
                        :title="t('reports.statistics.sales.title')"
                        :data="channelSlices"
                        :height="220"
                        :empty-text="t('reports.statistics.sales.title')"
                    />
                </Card>

                <div class="flex justify-end">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="
                            router.get(
                                route('reports.statistics'),
                                props.filters,
                            )
                        "
                    >
                        {{ t('reports.statistics.apply') }}
                    </Button>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
