<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ArrowDownToLine,
    ArrowUpFromLine,
    Boxes,
    CalendarRange,
    CircleDollarSign,
    Flame,
    Layers,
    Receipt,
    TrendingDown,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import MetricCard from '@/components/ui/MetricCard.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import {
    formatDateTime,
    formatMoney,
    formatMonth,
    formatNumber,
} from '@/lib/format';

type RecentMovement = {
    id: number;
    number: string;
    type: 'incoming' | 'outgoing' | 'adjustment';
    store_name: string | null;
    total_quantity: number;
    total_value: number;
    created_at: string;
};

type TopConsumedItem = {
    item_id: number;
    title: string;
    sku: string | null;
    total_quantity: number;
    total_value: number;
    rows_count: number;
};

type RecentStatement = {
    id: number;
    year: number;
    month: number;
    total: number;
};

type StockStatus = {
    in_stock: number;
    low_stock: number;
    out_of_stock: number;
};

const props = defineProps<{
    active_store: { id: number; name: string } | null;
    metrics: {
        inventory_value: number;
        items_count: number;
        low_stock_items: number;
        today_movements: number;
        month_incoming: number;
        month_outgoing: number;
    };
    stock_status: StockStatus;
    top_consumed: TopConsumedItem[];
    recent_movements: RecentMovement[];
    recent_statements: RecentStatement[];
}>();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

const totalTracked = computed(
    (): number =>
        props.stock_status.in_stock +
        props.stock_status.low_stock +
        props.stock_status.out_of_stock,
);

function statusPercent(count: number): number {
    if (totalTracked.value === 0) {
        return 0;
    }
    return Math.round((count / totalTracked.value) * 100);
}

function statementPeriodLabel(statement: RecentStatement): string {
    return formatMonth(statement.year, statement.month, locale.value);
}
</script>

<template>
    <AppLayout :title="t('dashboard.title')">
        <Head :title="t('dashboard.title')" />

        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('dashboard.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('dashboard.subtitle') }}
                    </p>
                </div>
                <p
                    v-if="props.active_store"
                    class="text-sm font-semibold text-on-surface-variant"
                    data-testid="active-store"
                >
                    {{ props.active_store.name }}
                </p>
            </header>

            <EmptyState
                v-if="!props.active_store"
                :title="t('dashboard.no_store.title')"
                :description="t('dashboard.no_store.description')"
            />

            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        :title="t('dashboard.metrics.inventory_value')"
                        :value="formatMoney(metrics.inventory_value)"
                    >
                        <template #icon>
                            <Layers :size="14" />
                        </template>
                    </MetricCard>
                    <MetricCard
                        :title="t('dashboard.metrics.items_count')"
                        :value="formatNumber(metrics.items_count)"
                    >
                        <template #icon>
                            <Boxes :size="14" />
                        </template>
                    </MetricCard>
                    <MetricCard
                        :title="t('dashboard.metrics.low_stock')"
                        :value="formatNumber(metrics.low_stock_items)"
                    >
                        <template #icon>
                            <TrendingDown :size="14" />
                        </template>
                    </MetricCard>
                    <MetricCard
                        :title="t('dashboard.metrics.today_movements')"
                        :value="formatNumber(metrics.today_movements)"
                    >
                        <template #icon>
                            <ArrowLeftRight :size="14" />
                        </template>
                    </MetricCard>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <Card padded>
                        <CardHeader>
                            <CardTitle>
                                <span class="flex items-center gap-2">
                                    <Receipt
                                        :size="14"
                                        class="text-on-surface-variant"
                                    />
                                    {{ t('dashboard.month_flow.title') }}
                                </span>
                            </CardTitle>
                            <CardDescription>
                                {{ t('dashboard.month_flow.subtitle') }}
                            </CardDescription>
                        </CardHeader>
                        <div class="grid grid-cols-2 gap-3">
                            <div
                                class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                            >
                                <div
                                    class="flex items-center gap-2 text-[10px] font-semibold tracking-wider text-on-surface-variant uppercase"
                                >
                                    <ArrowDownToLine :size="12" />
                                    {{ t('dashboard.month_flow.incoming') }}
                                </div>
                                <p
                                    class="mt-1 font-heading text-xl font-bold text-emerald-600"
                                >
                                    {{ formatMoney(metrics.month_incoming) }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                            >
                                <div
                                    class="flex items-center gap-2 text-[10px] font-semibold tracking-wider text-on-surface-variant uppercase"
                                >
                                    <ArrowUpFromLine :size="12" />
                                    {{ t('dashboard.month_flow.outgoing') }}
                                </div>
                                <p
                                    class="mt-1 font-heading text-xl font-bold text-rose-600"
                                >
                                    {{ formatMoney(metrics.month_outgoing) }}
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card padded>
                        <CardHeader>
                            <CardTitle>
                                <span class="flex items-center gap-2">
                                    <Boxes
                                        :size="14"
                                        class="text-on-surface-variant"
                                    />
                                    {{ t('dashboard.stock_status.title') }}
                                </span>
                            </CardTitle>
                            <CardDescription>
                                {{ t('dashboard.stock_status.subtitle') }}
                            </CardDescription>
                        </CardHeader>
                        <div class="flex flex-col gap-3">
                            <div
                                class="flex items-center justify-between gap-3 text-xs"
                            >
                                <span class="text-on-surface">
                                    {{ t('items.status.in_stock') }}
                                </span>
                                <span class="font-mono text-on-surface-variant">
                                    {{ formatNumber(stock_status.in_stock) }}
                                    <span
                                        class="ml-1 text-[10px] text-on-surface-variant/70"
                                    >
                                        ({{
                                            statusPercent(stock_status.in_stock)
                                        }}
                                        %)
                                    </span>
                                </span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-surface-container-low"
                            >
                                <div
                                    class="h-full bg-emerald-500"
                                    :style="{
                                        width:
                                            statusPercent(
                                                stock_status.in_stock,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>

                            <div
                                class="flex items-center justify-between gap-3 text-xs"
                            >
                                <span class="text-on-surface">
                                    {{ t('items.status.low_stock') }}
                                </span>
                                <span class="font-mono text-on-surface-variant">
                                    {{ formatNumber(stock_status.low_stock) }}
                                    <span
                                        class="ml-1 text-[10px] text-on-surface-variant/70"
                                    >
                                        ({{
                                            statusPercent(
                                                stock_status.low_stock,
                                            )
                                        }}
                                        %)
                                    </span>
                                </span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-surface-container-low"
                            >
                                <div
                                    class="h-full bg-amber-500"
                                    :style="{
                                        width:
                                            statusPercent(
                                                stock_status.low_stock,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>

                            <div
                                class="flex items-center justify-between gap-3 text-xs"
                            >
                                <span class="text-on-surface">
                                    {{ t('items.status.out_of_stock') }}
                                </span>
                                <span class="font-mono text-on-surface-variant">
                                    {{
                                        formatNumber(stock_status.out_of_stock)
                                    }}
                                    <span
                                        class="ml-1 text-[10px] text-on-surface-variant/70"
                                    >
                                        ({{
                                            statusPercent(
                                                stock_status.out_of_stock,
                                            )
                                        }}
                                        %)
                                    </span>
                                </span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-surface-container-low"
                            >
                                <div
                                    class="h-full bg-rose-500"
                                    :style="{
                                        width:
                                            statusPercent(
                                                stock_status.out_of_stock,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>
                        </div>
                    </Card>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <Card padded class="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>
                                <span class="flex items-center gap-2">
                                    <Flame
                                        :size="14"
                                        class="text-on-surface-variant"
                                    />
                                    {{ t('dashboard.top_consumed.title') }}
                                </span>
                            </CardTitle>
                            <CardDescription>
                                {{ t('dashboard.top_consumed.subtitle') }}
                            </CardDescription>
                        </CardHeader>
                        <EmptyState
                            v-if="top_consumed.length === 0"
                            :title="t('dashboard.top_consumed.empty')"
                        />
                        <div v-else class="overflow-x-auto">
                            <DataTable>
                                <thead>
                                    <tr>
                                        <th>
                                            {{
                                                t('dashboard.top_consumed.item')
                                            }}
                                        </th>
                                        <th class="text-right">
                                            {{
                                                t(
                                                    'dashboard.top_consumed.quantity',
                                                )
                                            }}
                                        </th>
                                        <th class="text-right">
                                            {{
                                                t(
                                                    'dashboard.top_consumed.value',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in top_consumed"
                                        :key="item.item_id"
                                    >
                                        <td
                                            class="font-semibold text-on-surface"
                                        >
                                            <div class="flex flex-col">
                                                <span>{{ item.title }}</span>
                                                <span
                                                    v-if="item.sku"
                                                    class="font-mono text-[10px] font-normal text-on-surface-variant"
                                                >
                                                    {{ item.sku }}
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            class="text-right font-mono text-xs text-on-surface-variant"
                                        >
                                            {{
                                                formatNumber(
                                                    item.total_quantity,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="text-right font-semibold text-on-surface"
                                        >
                                            {{ formatMoney(item.total_value) }}
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
                                    <CalendarRange
                                        :size="14"
                                        class="text-on-surface-variant"
                                    />
                                    {{ t('dashboard.statements.title') }}
                                </span>
                            </CardTitle>
                            <CardDescription>
                                {{ t('dashboard.statements.subtitle') }}
                            </CardDescription>
                        </CardHeader>
                        <EmptyState
                            v-if="recent_statements.length === 0"
                            :title="t('dashboard.statements.empty')"
                        />
                        <ul
                            v-else
                            class="flex flex-col divide-y divide-outline-glass"
                        >
                            <li
                                v-for="statement in recent_statements"
                                :key="statement.id"
                                class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <Link
                                    :href="
                                        route('statements.index', {
                                            year: statement.year,
                                            month: statement.month,
                                        })
                                    "
                                    class="group flex flex-1 items-center justify-between gap-3"
                                >
                                    <div class="flex flex-col">
                                        <span
                                            class="text-sm font-semibold text-on-surface group-hover:text-primary"
                                        >
                                            {{
                                                statementPeriodLabel(statement)
                                            }}
                                        </span>
                                        <span
                                            class="font-mono text-[10px] text-on-surface-variant"
                                        >
                                            #{{ formatNumber(statement.id) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-heading text-sm font-bold text-on-surface"
                                        >
                                            {{ formatMoney(statement.total) }}
                                        </span>
                                        <CircleDollarSign
                                            :size="14"
                                            class="text-on-surface-variant"
                                        />
                                    </div>
                                </Link>
                            </li>
                        </ul>
                    </Card>
                </div>

                <Card padded>
                    <CardHeader>
                        <CardTitle>{{ t('dashboard.recent.title') }}</CardTitle>
                        <CardDescription>{{
                            t('dashboard.recent.subtitle')
                        }}</CardDescription>
                    </CardHeader>
                    <EmptyState
                        v-if="recent_movements.length === 0"
                        :title="t('dashboard.recent.empty')"
                    />
                    <div v-else class="overflow-x-auto">
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>{{ t('dashboard.recent.number') }}</th>
                                    <th>{{ t('dashboard.recent.type') }}</th>
                                    <th class="text-right">
                                        {{ t('dashboard.recent.quantity') }}
                                    </th>
                                    <th class="text-right">
                                        {{ t('dashboard.recent.value') }}
                                    </th>
                                    <th>{{ t('dashboard.recent.date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="movement in recent_movements"
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
                                        <Badge :variant="movement.type">
                                            {{
                                                t(
                                                    `stock_movements.types.${movement.type}`,
                                                )
                                            }}
                                        </Badge>
                                    </td>
                                    <td
                                        class="text-right font-semibold text-on-surface"
                                    >
                                        {{
                                            formatNumber(
                                                movement.total_quantity,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="text-right text-on-surface-variant"
                                    >
                                        {{ formatMoney(movement.total_value) }}
                                    </td>
                                    <td class="text-xs text-on-surface-variant">
                                        {{
                                            formatDateTime(movement.created_at)
                                        }}
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
