<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowLeftRight } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import DataTable from '@/components/ui/DataTable.vue';
import MovementTypeBadge from '@/components/ui/MovementTypeBadge.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';

type Row = {
    id: number;
    item_id: number;
    item_title: string;
    item_sku: string | null;
    quantity: number | null;
    total: number;
    quantity_before: number | null;
    quantity_after: number | null;
    quantity_difference: number | null;
    adjustment_reason: string | null;
};

defineProps<{
    movement: {
        id: number;
        number: string;
        type: 'incoming' | 'outgoing' | 'adjustment';
        display_label_key: 'incoming' | 'outgoing' | 'transfer' | 'adjustment';
        note: string | null;
        store_id: number | null;
        store_name: string | null;
        source_store_id: number | null;
        source_store_name: string | null;
        total_quantity: number;
        total_value: number;
        created_by: string | null;
        created_at: string;
    };
    rows: Row[];
}>();

const { t } = useI18n();

useBoundLocale();
</script>

<template>
    <AppLayout :title="movement.number">
        <Head :title="movement.number" />

        <div class="flex flex-col gap-6">
            <div>
                <Link
                    :href="route('stock-movements.index')"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-primary"
                >
                    <ArrowLeft :size="12" />
                    {{ t('stock_movements.back_to_list') }}
                </Link>
            </div>

            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-3">
                        <h1
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ t('stock_movements.detail.title') }}
                            {{ movement.number }}
                        </h1>
                        <MovementTypeBadge
                            :type="movement.type"
                            :label-key="movement.display_label_key"
                        />
                    </div>
                    <p class="mt-2 text-sm text-on-surface-variant">
                        {{ formatDate(movement.created_at) }} ·
                        <span
                            v-if="
                                movement.type === 'outgoing' &&
                                movement.source_store_name
                            "
                            >{{ movement.source_store_name }} →
                        </span>
                        <span v-if="movement.store_name"
                            >{{ movement.store_name }} ·
                        </span>
                        <span v-if="movement.created_by">{{
                            movement.created_by
                        }}</span>
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('stock_movements.detail.total_quantity')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ formatNumber(movement.total_quantity) }}
                        </p>
                    </CardContent>
                </Card>
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('stock_movements.detail.total_value')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ formatMoney(movement.total_value) }}
                        </p>
                    </CardContent>
                </Card>
                <Card padded>
                    <CardHeader>
                        <CardDescription>{{
                            t('stock_movements.detail.items_count')
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                        >
                            {{ rows.length }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card padded>
                <CardHeader>
                    <CardTitle>
                        <span class="flex items-center gap-2">
                            <ArrowLeftRight
                                :size="14"
                                class="text-on-surface-variant"
                            />
                            {{ t('stock_movements.detail.items') }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <div class="overflow-x-auto">
                    <DataTable v-if="movement.type === 'adjustment'">
                        <thead>
                            <tr>
                                <th>{{ t('stock_movements.detail.item') }}</th>
                                <th>{{ t('stock_movements.detail.sku') }}</th>
                                <th class="text-right">
                                    {{ t('stock_movements.detail.before') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stock_movements.detail.after') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stock_movements.detail.difference') }}
                                </th>
                                <th>
                                    {{ t('stock_movements.detail.reason') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id">
                                <td class="font-semibold text-on-surface">
                                    {{ row.item_title }}
                                </td>
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ row.item_sku ?? '—' }}
                                </td>
                                <td class="text-right text-on-surface-variant">
                                    {{
                                        row.quantity_before !== null
                                            ? formatNumber(row.quantity_before)
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{
                                        row.quantity_after !== null
                                            ? formatNumber(row.quantity_after)
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="text-right font-semibold"
                                    :class="
                                        (row.quantity_difference ?? 0) >= 0
                                            ? 'text-emerald-600'
                                            : 'text-rose-600'
                                    "
                                >
                                    {{
                                        row.quantity_difference !== null
                                            ? formatNumber(
                                                  row.quantity_difference,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{
                                        row.adjustment_reason
                                            ? t(
                                                  `stock_movements.reasons.${row.adjustment_reason}`,
                                              )
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                    <DataTable v-else>
                        <thead>
                            <tr>
                                <th>{{ t('stock_movements.detail.item') }}</th>
                                <th>{{ t('stock_movements.detail.sku') }}</th>
                                <th class="text-right">
                                    {{ t('stock_movements.detail.quantity') }}
                                </th>
                                <th class="text-right">
                                    {{ t('stock_movements.detail.line_total') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id">
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
                                    {{
                                        row.quantity !== null
                                            ? formatNumber(row.quantity)
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.total) }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>

            <Card padded v-if="movement.note">
                <CardHeader>
                    <CardTitle>{{
                        t('stock_movements.detail.notes')
                    }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-on-surface">{{ movement.note }}</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
