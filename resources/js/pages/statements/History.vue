<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import BackLink from '@/components/ui/BackLink.vue';
import DataTable from '@/components/ui/DataTable.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatCzechDateTime } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';

type VersionRow = {
    id: number;
    snapshot_at: string;
    note: string | null;
    created_by: number | null;
    created_by_email: string | null;
    day_count: number;
};

const props = defineProps<{
    statement: {
        id: number;
        store_id: number;
        store_name: string;
        store_active: boolean;
        year: number;
        month: number;
    };
    rows: VersionRow[];
    filters: {
        store_id: number;
        year: number;
        month: number;
    };
    is_admin: boolean;
}>();

const { t } = useI18n();
const route = useRoute();

useBoundLocale();

const totals = computed(() => ({
    count: props.rows.length,
}));
</script>

<template>
    <AppLayout :title="t('statements.history.title')">
        <div class="flex flex-col gap-6">
            <header
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <BackLink
                        :href="
                            route('statements.index', {
                                store_id: props.filters.store_id,
                                year: props.filters.year,
                                month: props.filters.month,
                            })
                        "
                    >
                        {{ t('statements.history.back') }}
                    </BackLink>
                    <h1
                        class="mt-2 font-heading text-2xl font-bold tracking-tight text-on-surface"
                    >
                        {{ t('statements.history.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ t('statements.history.subtitle') }}
                    </p>
                    <StoreContextIndicator
                        :store="{ name: props.statement.store_name }"
                    />
                </div>
            </header>

            <section class="space-y-4">
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-on-surface-variant"
                >
                    <span>
                        <strong class="font-semibold text-on-surface">{{
                            totals.count
                        }}</strong>
                        {{ t('statements.history.versions_label') }}
                    </span>
                </div>

                <EmptyState
                    v-if="props.rows.length === 0"
                    :title="t('statements.history.empty.title')"
                    :description="t('statements.history.empty.description')"
                />

                <DataTable v-else density="compact">
                    <thead>
                        <tr>
                            <th class="min-w-[12rem] text-left">
                                {{
                                    t('statements.history.columns.snapshot_at')
                                }}
                            </th>
                            <th class="min-w-[8rem] text-right">
                                {{ t('statements.history.columns.day_count') }}
                            </th>
                            <th class="min-w-[14rem] text-left">
                                {{ t('statements.history.columns.note') }}
                            </th>
                            <th class="min-w-[14rem] text-left">
                                {{ t('statements.history.columns.created_by') }}
                            </th>
                            <th class="min-w-[8rem] text-right">
                                {{ t('statements.history.columns.open') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in props.rows" :key="row.id">
                            <td
                                class="font-mono text-xs text-on-surface-variant"
                            >
                                {{ formatCzechDateTime(row.snapshot_at) }}
                            </td>
                            <td
                                class="text-right font-semibold text-on-surface"
                            >
                                {{ row.day_count }}
                            </td>
                            <td class="text-xs text-on-surface-variant">
                                {{ row.note ?? '—' }}
                            </td>
                            <td class="text-xs text-on-surface-variant">
                                {{ row.created_by_email ?? '—' }}
                            </td>
                            <td class="text-right">
                                <Link
                                    :href="
                                        route('statements.versions.show', {
                                            version: row.id,
                                        })
                                    "
                                >
                                    <Button variant="secondary">
                                        {{ t('statements.history.open') }} →
                                    </Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </section>
        </div>
    </AppLayout>
</template>
