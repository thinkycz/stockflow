import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import { useDialog } from '@/composables/useDialog';
import { withActionErrorToast } from '@/lib/action-errors';

type VersionRow = {
    date: string;
    cash: number;
    card: number;
    wolt: number;
    bolt: number;
    bolt_cash: number;
    foodora: number;
    total: number;
    cash_checked: boolean;
};
export type StatementVersionProps = {
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
        store_active: boolean;
        year: number;
        month: number;
    };
    rows: VersionRow[];
    is_admin: boolean;
};

export function useStatementVersion(props: StatementVersionProps) {
    const { t } = useI18n();

    const route = useRoute();

    const dialog = useDialog();

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

    function rowCashTotal(row: VersionRow): number {
        return Number(row.cash || 0) + Number(row.bolt_cash || 0);
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

    async function restore(): Promise<void> {
        if (
            !(await dialog.confirm({
                title: t('statements.history.restore'),
                message: t('statements.history.confirm_restore'),
                confirmLabel: t('statements.history.restore'),
                variant: 'warning',
            }))
        )
            return;
        router.post(
            route('statements.versions.restore', { version: props.version.id }),
            {},
            withActionErrorToast({ preserveScroll: true }),
        );
    }
    return { t, route, rowCashTotal, totals, restore };
}
