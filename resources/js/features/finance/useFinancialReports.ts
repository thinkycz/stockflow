import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { formatNumber } from '@/lib/format';

type FinancialReport = {
    totals: {
        total_revenue: number;
        investment: number;
        card_provision: number;
        marketplace_provision: number;
        provisions: number;
        gross_margin: number;
        margin_percent: number;
        daily_average: number;
    };
    channels: Record<
        'cash' | 'card' | 'wolt' | 'bolt' | 'bolt_cash' | 'foodora',
        number
    >;
    daily: Array<{ label: string; value: number }>;
    days_with_revenue: number;
};

type InventoryItem = {
    item_id: number;
    title: string;
    sku: string | null;
    unit: string | null;
    current_quantity: number;
    consumed_quantity: number;
    avg_daily_consumption: number;
    projected_stockout_at: string | null;
    status: 'ok' | 'due_soon' | 'out' | 'no_data';
};

type InventoryReport = {
    as_of: string;
    current_inventory: {
        sku_count: number;
        value: number;
        value_is_estimate: boolean;
    };
    consumption: { value: number; affected_skus: number };
    flows: {
        receipts_value: number;
        receipts_count: number;
        transfer_in_value: number;
        transfer_in_count: number;
        transfer_out_value: number;
        transfer_out_count: number;
    };
    risk: { due_soon: number; out: number; no_data: number };
    data_quality: {
        last_inventory_at: string | null;
        average_coverage_days: number;
        covered_items: number;
    };
    classified_changes: Array<{
        classification: string;
        rows_count: number;
        value: number;
    }>;
    consumption_series: Array<{ label: string; value: number }>;
    items: InventoryItem[];
};
export type FinancialReportsProps = {
    active_store: { id: number; name: string } | null;
    filter: { store_id: number | null; year: number; month: number };
    summary: {
        total_revenue: number;
        gross_margin: number;
        consumption_cost: number;
        inventory_value: number;
    };
    financial_report: FinancialReport;
    inventory_report: InventoryReport;
};

export function useFinancialReports(props: FinancialReportsProps) {
    const { t } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const activeTab = ref<'finance' | 'inventory'>('finance');

    const reportTabs = computed(() => [
        { value: 'finance', label: t('reports.tabs.finance') },
        { value: 'inventory', label: t('reports.tabs.inventory') },
    ]);

    const monthValue = computed(
        () =>
            `${props.filter.year}-${String(props.filter.month).padStart(2, '0')}`,
    );

    const channelData = computed(() => [
        {
            key: 'cash',
            label: t('statements.columns.cash'),
            value: props.financial_report.channels.cash,
            color: '#16a34a',
        },
        {
            key: 'card',
            label: t('statements.columns.card'),
            value: props.financial_report.channels.card,
            color: '#1f6feb',
        },
        {
            key: 'wolt',
            label: t('statements.columns.wolt'),
            value: props.financial_report.channels.wolt,
            color: '#f59e0b',
        },
        {
            key: 'bolt',
            label: t('statements.columns.bolt'),
            value: props.financial_report.channels.bolt,
            color: '#7c3aed',
        },
        {
            key: 'bolt_cash',
            label: t('statements.columns.bolt_cash'),
            value: props.financial_report.channels.bolt_cash,
            color: '#db2777',
        },
        {
            key: 'foodora',
            label: t('statements.columns.foodora'),
            value: props.financial_report.channels.foodora,
            color: '#0891b2',
        },
    ]);

    function selectMonth(value: string): void {
        const [year, month] = value.split('-').map(Number);
        if (!year || !month) return;
        router.get(
            route('reports.index'),
            { store_id: props.filter.store_id, year, month },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    function quantityWithUnit(value: number, unit: string | null): string {
        return `${formatNumber(value)}${unit ? ` ${unit}` : ''}`;
    }
    return {
        t,
        activeTab,
        reportTabs,
        monthValue,
        channelData,
        selectMonth,
        quantityWithUnit,
    };
}
