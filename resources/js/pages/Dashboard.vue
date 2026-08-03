<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ArrowRight,
    Boxes,
    Clock3,
    ClipboardCheck,
    ClipboardList,
    Coffee,
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
import DashboardChecklistSection from '@/components/checklists/DashboardChecklistSection.vue';
import type { ChecklistDashboardPayload } from '@/components/checklists/DashboardChecklistSection.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import {
    formatDateTime,
    formatDate,
    formatMoney,
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
        low_stock_items: number;
        today_movements: number;
        last_inventory_at: string | null;
    } | null;
    recent_movements: RecentMovement[];
    operations: Operations | null;
    is_admin: boolean;
    noticeboard: NoticeboardPayload;
    checklists: ChecklistDashboardPayload | null;
}>();

const { t } = useI18n();

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
</script>

<template>
    <AppLayout :title="t('dashboard.title')">
        <div class="flex flex-col gap-6">
            <NoticeboardSection
                :noticeboard="props.noticeboard"
                :active-store="props.active_store"
            />

            <DashboardChecklistSection
                v-if="props.checklists"
                :checklists="props.checklists"
            />

            <template v-if="!props.is_admin">
                <section v-if="operations" class="flex flex-col gap-4">
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

            <template v-else-if="metrics">
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

                <section class="space-y-4">
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
                    <DataTable v-else>
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
                                    <MovementTypeBadge :type="movement.type" />
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
                </section>
            </template>
        </div>
    </AppLayout>
</template>
