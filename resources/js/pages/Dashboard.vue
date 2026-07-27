<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ArrowDownToLine,
    ArrowRight,
    ArrowUpFromLine,
    Boxes,
    CalendarRange,
    CircleDollarSign,
    Clock3,
    ClipboardCheck,
    ClipboardList,
    Coffee,
    Flame,
    Layers,
    PackageMinus,
    PackagePlus,
    Receipt,
    TrendingDown,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import MetricCard from '@/components/ui/MetricCard.vue';
import MovementTypeBadge from '@/components/ui/MovementTypeBadge.vue';
import NoticeboardSection from '@/components/noticeboard/NoticeboardSection.vue';
import type { NoticeboardPayload } from '@/components/noticeboard/NoticeboardSection.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import {
    formatDateTime,
    formatDate,
    formatMoney,
    formatMonth,
    formatNumber,
} from '@/lib/format';

type RecentMovement = {
    id: number;
    number: string;
    type:
        | 'incoming'
        | 'transfer'
        | 'consumption'
        | 'adjustment'
        | 'inventory_reconciliation'
        | 'reversal';
    store_name: string | null;
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
    no_data: number;
};

type Operations = {
    current_shifts: Array<{
        id: number;
        worker_name: string;
        start_time: string;
        end_time: string;
    }>;
    next_shift: {
        id: number;
        worker_name: string;
        date: string;
        start_time: string;
        end_time: string;
    } | null;
    attendance: {
        workers: Array<{
            worker_name: string;
            status: 'present' | 'break';
        }>;
        stale_count: number;
    };
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
        last_inventory_at: string | null;
    } | null;
    stock_status: StockStatus | null;
    top_consumed: TopConsumedItem[];
    recent_movements: RecentMovement[];
    recent_statements: RecentStatement[];
    operations: Operations | null;
    is_admin: boolean;
    noticeboard: NoticeboardPayload;
}>();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

const limitedActions = computed(() => [
    {
        key: 'incoming',
        href: route('stock-movements.create', { mode: 'incoming' }),
        title: t('dashboard.actions.incoming.title'),
        description: t('dashboard.actions.incoming.description'),
        icon: PackagePlus,
    },
    {
        key: 'consumption',
        href: route('stock-movements.create', { mode: 'consumption' }),
        title: t('dashboard.actions.consumption.title'),
        description: t('dashboard.actions.consumption.description'),
        icon: PackageMinus,
    },
    {
        key: 'statements',
        href: route('statements.index'),
        title: t('dashboard.actions.statements.title'),
        description: t('dashboard.actions.statements.description'),
        icon: Receipt,
    },
    {
        key: 'inventory',
        href: route('inventory-counts.index'),
        title: t('dashboard.actions.inventory.title'),
        description: t('dashboard.actions.inventory.description'),
        icon: ClipboardList,
    },
]);

const totalTracked = computed(
    (): number =>
        (props.stock_status?.in_stock ?? 0) +
        (props.stock_status?.low_stock ?? 0) +
        (props.stock_status?.out_of_stock ?? 0) +
        (props.stock_status?.no_data ?? 0),
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
    <AppLayout :title="t('noticeboard.title')">
        <div class="flex flex-col gap-6">
            <NoticeboardSection
                :noticeboard="props.noticeboard"
                :active-store="props.active_store"
            />

            <template v-if="!props.is_admin">
                <section v-if="operations" class="flex flex-col gap-4">
                    <div>
                        <h2
                            class="font-heading text-lg font-bold text-on-surface"
                        >
                            {{ t('dashboard.operations.title') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('dashboard.operations.subtitle') }}
                        </p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <Card padded>
                            <div class="flex items-center gap-2">
                                <Users :size="17" class="text-primary" />
                                <h3
                                    class="font-heading text-sm font-bold text-on-surface"
                                >
                                    {{
                                        t('dashboard.operations.current_shift')
                                    }}
                                </h3>
                            </div>
                            <p
                                v-if="operations.current_shifts.length === 0"
                                class="mt-4 text-sm text-on-surface-variant"
                            >
                                {{ t('dashboard.operations.no_current_shift') }}
                            </p>
                            <ul v-else class="mt-4 space-y-3">
                                <li
                                    v-for="shift in operations.current_shifts"
                                    :key="shift.id"
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span
                                        class="text-sm font-semibold text-on-surface"
                                    >
                                        {{ shift.worker_name }}
                                    </span>
                                    <span
                                        class="font-mono text-xs text-on-surface-variant"
                                    >
                                        {{ shift.start_time }}–{{
                                            shift.end_time
                                        }}
                                    </span>
                                </li>
                            </ul>
                        </Card>

                        <Card padded>
                            <div class="flex items-center gap-2">
                                <Clock3 :size="17" class="text-primary" />
                                <h3
                                    class="font-heading text-sm font-bold text-on-surface"
                                >
                                    {{ t('dashboard.operations.next_shift') }}
                                </h3>
                            </div>
                            <p
                                v-if="!operations.next_shift"
                                class="mt-4 text-sm text-on-surface-variant"
                            >
                                {{ t('dashboard.operations.no_next_shift') }}
                            </p>
                            <div v-else class="mt-4">
                                <p
                                    class="text-sm font-semibold text-on-surface"
                                >
                                    {{ operations.next_shift.worker_name }}
                                </p>
                                <p class="mt-1 text-xs text-on-surface-variant">
                                    {{ formatDate(operations.next_shift.date) }}
                                    · {{ operations.next_shift.start_time }}–{{
                                        operations.next_shift.end_time
                                    }}
                                </p>
                            </div>
                        </Card>

                        <Card padded>
                            <div class="flex items-center gap-2">
                                <ClipboardCheck
                                    :size="17"
                                    class="text-primary"
                                />
                                <h3
                                    class="font-heading text-sm font-bold text-on-surface"
                                >
                                    {{ t('dashboard.operations.attendance') }}
                                </h3>
                            </div>
                            <p
                                v-if="
                                    operations.attendance.workers.length === 0
                                "
                                class="mt-4 text-sm text-on-surface-variant"
                            >
                                {{ t('dashboard.operations.no_attendance') }}
                            </p>
                            <ul v-else class="mt-4 space-y-3">
                                <li
                                    v-for="worker in operations.attendance
                                        .workers"
                                    :key="worker.worker_name"
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span
                                        class="flex items-center gap-2 text-sm font-semibold text-on-surface"
                                    >
                                        <span
                                            class="size-2 rounded-full"
                                            :class="
                                                worker.status === 'break'
                                                    ? 'bg-amber-500'
                                                    : 'bg-emerald-500'
                                            "
                                        ></span>
                                        {{ worker.worker_name }}
                                    </span>
                                    <span
                                        class="flex items-center gap-1 text-xs text-on-surface-variant"
                                    >
                                        <Coffee
                                            v-if="worker.status === 'break'"
                                            :size="13"
                                        />
                                        {{
                                            t(
                                                `dashboard.operations.status.${worker.status}`,
                                            )
                                        }}
                                    </span>
                                </li>
                            </ul>
                            <p
                                v-if="operations.attendance.stale_count > 0"
                                class="mt-4 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-700"
                            >
                                {{
                                    t('dashboard.operations.stale_attendance', {
                                        count: operations.attendance
                                            .stale_count,
                                    })
                                }}
                            </p>
                        </Card>
                    </div>
                </section>

                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <Link
                        v-for="action in limitedActions"
                        :key="action.key"
                        :href="action.href"
                        class="group flex items-center gap-3 rounded-2xl border border-outline-glass bg-surface-container-lowest p-3 shadow-sm transition hover:border-primary/35 hover:shadow-md focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-on-primary"
                        >
                            <component :is="action.icon" :size="18" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="flex items-center justify-between gap-3 font-heading text-sm font-bold text-on-surface"
                            >
                                {{ action.title }}
                                <ArrowRight
                                    :size="15"
                                    class="shrink-0 text-on-surface-variant transition group-hover:translate-x-1 group-hover:text-primary"
                                />
                            </span>
                        </span>
                    </Link>
                </div>
            </template>

            <EmptyState
                v-else-if="!props.active_store"
                :title="t('dashboard.no_store.title')"
                :description="t('dashboard.no_store.description')"
            />

            <template v-else-if="metrics && stock_status">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        :title="t('dashboard.metrics.inventory_value')"
                        :value="formatMoney(metrics.inventory_value)"
                    >
                        <template #icon>
                            <Layers :size="14" />
                        </template>
                    </MetricCard>
                    <MetricCard
                        :title="t('dashboard.metrics.last_inventory')"
                        :value="
                            metrics.last_inventory_at
                                ? formatDateTime(metrics.last_inventory_at)
                                : '—'
                        "
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

                <div class="flex justify-end">
                    <Link
                        :href="route('reports.statistics')"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
                    >
                        {{ t('nav.statistics') }}
                        <ArrowRight :size="15" />
                    </Link>
                </div>

                <div class="hidden">
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

                            <div
                                class="flex items-center justify-between gap-3 text-xs"
                            >
                                <span class="text-on-surface">
                                    {{ t('items.status.no_data') }}
                                </span>
                                <span class="font-mono text-on-surface-variant">
                                    {{ formatNumber(stock_status.no_data) }}
                                    <span
                                        class="ml-1 text-[10px] text-on-surface-variant/70"
                                    >
                                        ({{
                                            statusPercent(stock_status.no_data)
                                        }}
                                        %)
                                    </span>
                                </span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-surface-container-low"
                            >
                                <div
                                    class="h-full bg-slate-400"
                                    :style="{
                                        width:
                                            statusPercent(
                                                stock_status.no_data,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>
                        </div>
                    </Card>
                </div>

                <div class="hidden">
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
                                    <th>
                                        {{ t('dashboard.recent.number') }}
                                    </th>
                                    <th>
                                        {{ t('dashboard.recent.type') }}
                                    </th>
                                    <th class="text-right">
                                        {{ t('dashboard.recent.value') }}
                                    </th>
                                    <th>
                                        {{ t('dashboard.recent.date') }}
                                    </th>
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
                                        <MovementTypeBadge
                                            :type="movement.type"
                                        />
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
