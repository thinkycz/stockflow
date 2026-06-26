<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import DataTable from '@/components/ui/DataTable.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { formatCzechDateTime } from '@/composables/useCzechDate';
import { useRoute } from '@/composables/useRoute';
import { formatMoney } from '@/lib/format';

type VersionRow = {
    date: string;
    cash: number;
    card: number;
    wolt: number;
    bolt: number;
    bolt_cash: number;
    foodora: number;
    total: number;
};

const props = defineProps<{
    version: {
        id: number;
        snapshot_at: string;
        note: string | null;
        created_by: number | null;
        created_by_email: string | null;
    };
    statement: {
        id: number;
        store_id: number;
        store_name: string;
        year: number;
        month: number;
    };
    rows: VersionRow[];
    is_admin: boolean;
}>();

const { t } = useI18n();
const route = useRoute();

useBoundLocale();

function rowTotal(row: VersionRow): number {
    return (
        Number(row.cash || 0) +
        Number(row.card || 0) +
        Number(row.wolt || 0) +
        Number(row.bolt || 0) +
        Number(row.bolt_cash || 0) +
        Number(row.foodora || 0)
    );
}

function totals(): {
    cash: number;
    card: number;
    wolt: number;
    bolt: number;
    bolt_cash: number;
    foodora: number;
    total: number;
} {
    let cash = 0;
    let card = 0;
    let wolt = 0;
    let bolt = 0;
    let boltCash = 0;
    let foodora = 0;
    let total = 0;
    for (const row of props.rows) {
        cash += Number(row.cash || 0);
        card += Number(row.card || 0);
        wolt += Number(row.wolt || 0);
        bolt += Number(row.bolt || 0);
        boltCash += Number(row.bolt_cash || 0);
        foodora += Number(row.foodora || 0);
        total += rowTotal(row);
    }
    return {
        cash,
        card,
        wolt,
        bolt,
        bolt_cash: boltCash,
        foodora,
        total,
    };
}

function restore(): void {
    if (!window.confirm(t('statements.history.confirm_restore'))) {
        return;
    }
    router.post(
        route('statements.versions.restore', { version: props.version.id }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout :title="`${t('statements.session.title')} #${version.id}`">
        <Head :title="`${t('statements.session.title')} #${version.id}`" />

        <div class="flex flex-col gap-6">
            <div>
                <Link
                    :href="
                        route('statements.history', {
                            statement: props.statement.id,
                        })
                    "
                    class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-primary"
                >
                    <ArrowLeft :size="12" />
                    {{ t('statements.history.back') }}
                </Link>
            </div>

            <div class="flex flex-col gap-2">
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('statements.session.title') }}
                    <span class="text-on-surface-variant"
                        >#{{ version.id }}</span
                    >
                </h1>
                <div
                    class="flex flex-wrap items-center gap-2 text-xs text-on-surface-variant"
                >
                    <span>{{ formatCzechDateTime(version.snapshot_at) }}</span>
                    <span>·</span>
                    <span class="font-semibold text-on-surface">{{
                        statement.store_name
                    }}</span>
                    <template v-if="version.created_by_email">
                        <span>·</span>
                        <span>{{ version.created_by_email }}</span>
                    </template>
                </div>
            </div>

            <Card padded>
                <div class="overflow-x-auto">
                    <DataTable class="[&_td]:px-2 [&_th]:px-2">
                        <thead>
                            <tr>
                                <th class="min-w-[6rem]">
                                    {{ t('statements.columns.day') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.cash') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.card') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.wolt') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.bolt') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.bolt_cash') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.foodora') }}
                                </th>
                                <th class="min-w-[7rem] text-right">
                                    {{ t('statements.columns.total') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.rows"
                                :key="row.date"
                                class="border-b border-outline-glass/40 last:border-b-0"
                            >
                                <td
                                    class="font-mono text-xs text-on-surface-variant"
                                >
                                    {{ row.date }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.cash) }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.card) }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.wolt) }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.bolt) }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.bolt_cash) }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.foodora) }}
                                </td>
                                <td
                                    class="text-right font-semibold text-on-surface"
                                >
                                    {{ formatMoney(row.total) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th
                                    class="border-t border-outline-glass pt-2 text-left text-xs font-semibold text-on-surface-variant"
                                >
                                    Σ
                                </th>
                                <th
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals().cash) }}
                                </th>
                                <th
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals().card) }}
                                </th>
                                <th
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals().wolt) }}
                                </th>
                                <th
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals().bolt) }}
                                </th>
                                <th
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals().bolt_cash) }}
                                </th>
                                <th
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface-variant"
                                >
                                    {{ formatMoney(totals().foodora) }}
                                </th>
                                <th
                                    class="border-t border-outline-glass pt-2 text-right text-xs font-semibold text-on-surface"
                                >
                                    {{ formatMoney(totals().total) }}
                                </th>
                            </tr>
                        </tfoot>
                    </DataTable>
                </div>

                <div
                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end"
                >
                    <button
                        type="button"
                        class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-on-primary transition hover:bg-primary/90"
                        @click="restore"
                    >
                        {{ t('statements.history.restore') }}
                    </button>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
