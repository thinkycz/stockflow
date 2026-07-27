<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Card from '@/components/ui/Card.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Chart from '@/components/ui/Chart.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Select from '@/components/ui/Select.vue';
import StoreContextIndicator from '@/components/ui/StoreContextIndicator.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatMoney, formatMonth } from '@/lib/format';

type StatementChannel = {
    cash: number;
    card: number;
    wolt: number;
    bolt: number;
    bolt_cash: number;
    foodora: number;
};

type StatementTotals = {
    total_revenue: number;
    investment: number;
    card_provision: number;
    marketplace_provision: number;
    provisions: number;
    gross_margin: number;
    margin_percent: number;
    daily_average: number;
};

type StatementReport = {
    period: {
        store_id: number | null;
        year: number | null;
        month: number | null;
    };
    totals: StatementTotals;
    channels: StatementChannel;
    daily: Array<{ label: string; value: number }>;
    days_with_revenue: number;
    inventory_coverage: {
        average_days: number;
        covered_items: number;
        last_inventory_at: string | null;
    };
};

const props = defineProps<{
    active_store: { id: number; name: string } | null;
    statement_report: StatementReport;
    statement_filter: {
        all_time: boolean;
        store_id: number | null;
        year: number | null;
        month: number | null;
    };
}>();

const { t, locale } = useI18n();

useBoundLocale();

const route = useRoute();

const monthValue = computed((): string => {
    if (
        props.statement_filter.year === null ||
        props.statement_filter.month === null
    ) {
        return '';
    }
    const month = String(props.statement_filter.month).padStart(2, '0');
    return `${props.statement_filter.year}-${month}`;
});

const periodLabel = computed((): string => {
    if (props.statement_filter.all_time) {
        return t('reports.statements.period_all_time');
    }
    if (
        props.statement_filter.year !== null &&
        props.statement_filter.month !== null
    ) {
        return formatMonth(
            props.statement_filter.year,
            props.statement_filter.month,
            locale.value,
        );
    }
    return '—';
});

const months = computed(() => {
    const now = new Date();
    const result: Array<{ value: string; label: string }> = [];
    for (let offset = 0; offset < 12; offset++) {
        const date = new Date(now.getFullYear(), now.getMonth() - offset, 1);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const label = formatMonth(year, Number(month), locale.value);
        result.push({ value: `${year}-${month}`, label });
    }
    return result;
});

const channelData = computed(() => [
    {
        key: 'cash',
        label: t('statements.columns.cash'),
        value: props.statement_report.channels.cash,
        color: '#16a34a',
    },
    {
        key: 'card',
        label: t('statements.columns.card'),
        value: props.statement_report.channels.card,
        color: '#1f6feb',
    },
    {
        key: 'wolt',
        label: t('statements.columns.wolt'),
        value: props.statement_report.channels.wolt,
        color: '#f59e0b',
    },
    {
        key: 'bolt',
        label: t('statements.columns.bolt'),
        value: props.statement_report.channels.bolt,
        color: '#7c3aed',
    },
    {
        key: 'bolt_cash',
        label: t('statements.columns.bolt_cash'),
        value: props.statement_report.channels.bolt_cash,
        color: '#db2777',
    },
    {
        key: 'foodora',
        label: t('statements.columns.foodora'),
        value: props.statement_report.channels.foodora,
        color: '#0891b2',
    },
]);

const dailyRevenueData = computed(() => props.statement_report.daily);

function applyFilter(payload: Record<string, string | number | null>): void {
    router.get(route('reports.index'), payload, {
        preserveState: true,
        preserveScroll: true,
    });
}

function selectMonth(value: string | number | null | undefined): void {
    const raw = value === null || value === undefined ? '' : String(value);
    const [year, month] = raw.split('-').map((part: string) => Number(part));
    if (!year || !month) {
        return;
    }
    applyFilter({
        all_time: '0',
        year,
        month,
    });
}

function toggleAllTime(): void {
    applyFilter({
        all_time: props.statement_filter.all_time ? '0' : '1',
        year: props.statement_filter.year,
        month: props.statement_filter.month,
    });
}
</script>

<template>
    <AppLayout :title="t('reports.title')">
        <Head :title="t('reports.title')" />

        <div class="flex flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-2xl font-bold tracking-tight text-on-surface"
                >
                    {{ t('reports.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('reports.subtitle') }}
                </p>
                <StoreContextIndicator />
            </header>

            <EmptyState
                v-if="!props.active_store"
                :title="t('reports.no_store.title')"
                :description="t('reports.no_store.description')"
            />

            <template v-else>
                <Card padded>
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div class="space-y-2 sm:min-w-[12rem]">
                            <label
                                for="statement_month_filter"
                                class="text-xs font-semibold text-on-surface-variant"
                            >
                                {{ t('reports.statements.month') }}
                            </label>
                            <Select
                                id="statement_month_filter"
                                :model-value="monthValue"
                                :options="months"
                                :disabled="props.statement_filter.all_time"
                                @update:model-value="selectMonth"
                            />
                        </div>
                        <label
                            class="inline-flex cursor-pointer items-center gap-2 self-start rounded-lg border border-outline-glass bg-surface-container-lowest px-3 py-2 text-xs font-semibold text-on-surface"
                        >
                            <input
                                type="checkbox"
                                :checked="props.statement_filter.all_time"
                                class="h-4 w-4 rounded border-outline-glass text-primary focus:ring-primary"
                                @change="toggleAllTime"
                            />
                            {{ t('reports.statements.all_time') }}
                        </label>
                    </div>
                </Card>

                <Card padded>
                    <CardHeader>
                        <CardTitle>
                            <span class="flex items-center gap-2">
                                <Receipt
                                    :size="14"
                                    class="text-on-surface-variant"
                                />
                                {{ t('reports.statements.title') }}
                            </span>
                        </CardTitle>
                        <CardDescription>
                            {{ t('reports.statements.subtitle') }}
                        </CardDescription>
                    </CardHeader>

                    <p
                        class="mb-4 text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                    >
                        {{ periodLabel }}
                    </p>

                    <div
                        class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7"
                    >
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('reports.statements.total_revenue') }}
                            </p>
                            <p
                                class="mt-1 font-heading text-lg font-bold text-on-surface"
                            >
                                {{
                                    formatMoney(
                                        props.statement_report.totals
                                            .total_revenue,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('reports.statements.investment') }}
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-mono text-on-surface-variant"
                            >
                                {{
                                    t('reports.statements.inventory_coverage', {
                                        days: props.statement_report
                                            .inventory_coverage.average_days,
                                        items: props.statement_report
                                            .inventory_coverage.covered_items,
                                    })
                                }}
                            </p>
                            <p
                                class="mt-1 font-heading text-lg font-bold text-on-surface"
                            >
                                {{
                                    formatMoney(
                                        props.statement_report.totals
                                            .investment,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('reports.statements.provisions') }}
                            </p>
                            <p
                                class="mt-1 font-heading text-lg font-bold text-on-surface"
                            >
                                {{
                                    formatMoney(
                                        props.statement_report.totals
                                            .provisions,
                                    )
                                }}
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-mono text-on-surface-variant"
                            >
                                {{ t('reports.statements.card_provision') }}:
                                {{
                                    formatMoney(
                                        props.statement_report.totals
                                            .card_provision,
                                    )
                                }}
                            </p>
                            <p
                                class="text-[10px] font-mono text-on-surface-variant"
                            >
                                {{
                                    t(
                                        'reports.statements.marketplace_provision',
                                    )
                                }}:
                                {{
                                    formatMoney(
                                        props.statement_report.totals
                                            .marketplace_provision,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('reports.statements.gross_margin') }}
                            </p>
                            <p
                                class="mt-1 font-heading text-lg font-bold"
                                :class="
                                    props.statement_report.totals
                                        .gross_margin >= 0
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{
                                    formatMoney(
                                        props.statement_report.totals
                                            .gross_margin,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('reports.statements.margin_percent') }}
                            </p>
                            <p
                                class="mt-1 font-heading text-lg font-bold"
                                :class="
                                    props.statement_report.totals
                                        .margin_percent >= 0
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{
                                    props.statement_report.totals.margin_percent
                                }}
                                %
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('reports.statements.daily_average') }}
                            </p>
                            <p
                                class="mt-1 font-heading text-lg font-bold text-on-surface"
                            >
                                {{
                                    formatMoney(
                                        props.statement_report.totals
                                            .daily_average,
                                    )
                                }}
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-mono text-on-surface-variant"
                            >
                                {{ t('reports.statements.days_with_revenue') }}:
                                {{ props.statement_report.days_with_revenue }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-outline-glass bg-surface-container-low p-4"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                            >
                                {{ t('reports.statements.cash_share') }}
                            </p>
                            <p
                                class="mt-1 font-heading text-lg font-bold text-on-surface"
                            >
                                {{
                                    props.statement_report.totals
                                        .total_revenue > 0
                                        ? (
                                              (props.statement_report.channels
                                                  .cash /
                                                  props.statement_report.totals
                                                      .total_revenue) *
                                              100
                                          ).toFixed(1)
                                        : '0.0'
                                }}
                                %
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-mono text-on-surface-variant"
                            >
                                {{
                                    t('reports.statements.cash_share_subtitle')
                                }}
                            </p>
                        </div>
                    </div>

                    <div v-if="!props.statement_filter.all_time" class="mb-4">
                        <Chart
                            type="line"
                            :title="t('reports.statements.daily_revenue')"
                            :data="dailyRevenueData"
                            :empty-text="t('reports.statements.empty')"
                        />
                    </div>

                    <div
                        class="grid gap-4"
                        :class="
                            props.statement_filter.all_time
                                ? 'lg:grid-cols-1'
                                : 'lg:grid-cols-2'
                        "
                    >
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
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
