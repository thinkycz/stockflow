<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatCzechDateTime } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';

type SessionRow = {
    item_id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    current: number;
    previous: number | null;
    note: string | null;
};

defineProps<{
    session: {
        id: number;
        store_id: number;
        store_name: string;
        counted_at: string;
        note: string | null;
        created_by: number | null;
        created_by_email: string | null;
    };
    rows: SessionRow[];
}>();

const { t } = useI18n();

useBoundLocale();

const route = useRoute();
</script>

<template>
    <AppLayout :title="`${t('inventory_counts.session.title')} #${session.id}`">
        <Head
            :title="`${t('inventory_counts.session.title')} #${session.id}`"
        />

        <div class="flex flex-col gap-6">
            <div>
                <Link
                    :href="route('inventory-counts.index')"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-primary"
                >
                    <ArrowLeft :size="12" />
                    {{ t('inventory_counts.back_to_list') }}
                </Link>
            </div>

            <div class="flex flex-col gap-2">
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('inventory_counts.session.title') }}
                    <span class="text-on-surface-variant"
                        >#{{ session.id }}</span
                    >
                </h1>
                <div
                    class="flex flex-wrap items-center gap-2 text-xs text-on-surface-variant"
                >
                    <span>{{ formatCzechDateTime(session.counted_at) }}</span>
                    <span>·</span>
                    <span class="font-semibold text-on-surface">{{
                        session.store_name
                    }}</span>
                    <span v-if="session.created_by_email">·</span>
                    <span v-if="session.created_by_email">{{
                        session.created_by_email
                    }}</span>
                </div>
                <p
                    v-if="session.note"
                    class="mt-1 rounded-xl border border-outline-glass bg-surface-container-lowest px-3 py-2 text-xs text-on-surface-variant"
                >
                    {{ session.note }}
                </p>
            </div>

            <Card padded>
                <div class="overflow-x-auto">
                    <DataTable class="[&_td]:px-2 [&_th]:px-2">
                        <thead>
                            <tr>
                                <th class="min-w-[12rem] text-left">
                                    {{ t('inventory_counts.columns.item') }}
                                </th>
                                <th class="min-w-[8rem] text-left">
                                    {{ t('inventory_counts.columns.sku') }}
                                </th>
                                <th class="min-w-[5rem] text-left">
                                    {{ t('inventory_counts.columns.unit') }}
                                </th>
                                <th class="min-w-[8rem] text-right">
                                    {{ t('inventory_counts.columns.previous') }}
                                </th>
                                <th class="min-w-[9rem] text-right">
                                    {{
                                        t(
                                            'inventory_counts.columns.new_quantity',
                                        )
                                    }}
                                </th>
                                <th class="min-w-[12rem] text-left">
                                    {{ t('inventory_counts.columns.note') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in rows"
                                :key="row.item_id"
                                class="border-b border-outline-glass/40 last:border-b-0"
                            >
                                <td class="font-semibold text-on-surface">
                                    {{ row.title }}
                                </td>
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ row.sku ?? '—' }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ row.unit ?? '—' }}
                                </td>
                                <td
                                    class="text-right text-xs text-on-surface-variant"
                                >
                                    {{ row.previous ?? '—' }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ row.current }}
                                </td>
                                <td class="text-xs text-on-surface-variant">
                                    {{ row.note ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
