import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';

export function useBankStatementActions() {
    const { t } = useI18n();
    const dialog = useDialog();
    const route = useRoute();
    const busy = ref(false);
    async function deleteStatement(id: number): Promise<void> {
        if (
            busy.value ||
            !(await dialog.confirm({
                title: t('bank_statements.actions.delete'),
                message: t('bank_statements.delete_message'),
                confirmLabel: t('bank_statements.actions.delete'),
                variant: 'danger',
            }))
        )
            return;
        busy.value = true;
        router.delete(
            route('bank-statements.destroy', { bankStatement: id }),
            withActionErrorToast({
                preserveState: false,
                onFinish: () => {
                    busy.value = false;
                },
            }),
        );
    }
    async function reanalyze(id: number): Promise<void> {
        if (
            busy.value ||
            !(await dialog.confirm({
                title: t('bank_statements.actions.reanalyze'),
                message: t('bank_statements.reanalyze_message'),
                confirmLabel: t('bank_statements.actions.reanalyze'),
                variant: 'warning',
            }))
        )
            return;
        busy.value = true;
        router.post(
            route('bank-statements.retry', { bankStatement: id }),
            {},
            withActionErrorToast({
                preserveState: false,
                onFinish: () => {
                    busy.value = false;
                },
            }),
        );
    }
    return { busy, deleteStatement, reanalyze };
}
