<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftRight, Boxes, Layers, TrendingDown } from '@lucide/vue';
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
import { formatDateTime, formatMoney, formatNumber } from '@/lib/format';

type RecentMovement = {
    id: number;
    number: string;
    type: 'incoming' | 'outgoing' | 'adjustment';
    store_name: string | null;
    total_quantity: number;
    total_value: number;
    created_at: string;
};

defineProps<{
    metrics: {
        total_inventory_value: number;
        total_items: number;
        low_stock_items: number;
        today_movements: number;
    };
    recent_movements: RecentMovement[];
}>();

const { t } = useI18n();

useBoundLocale();
</script>

<template>
    <AppLayout :title="t('dashboard.title')">
        <Head :title="t('dashboard.title')" />

        <div class="flex flex-col gap-6">
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

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard
                    :title="t('dashboard.metrics.total_inventory_value')"
                    :value="formatMoney(metrics.total_inventory_value)"
                >
                    <template #icon>
                        <Layers :size="14" />
                    </template>
                </MetricCard>
                <MetricCard
                    :title="t('dashboard.metrics.total_items')"
                    :value="formatNumber(metrics.total_items)"
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
                                <th>{{ t('dashboard.recent.store') }}</th>
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
                                        :href="`/stock-movements/${movement.id}`"
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
                                <td class="text-xs text-on-surface-variant">
                                    {{ movement.store_name ?? '—' }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatNumber(movement.total_quantity) }}
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
