import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useDialog } from '@/composables/useDialog';
import { useRoute } from '@/composables/useRoute';
import { withActionErrorToast } from '@/lib/action-errors';
import type {
    GiftVoucherBatch,
    GiftVoucherRow,
    GiftVoucherStatus,
} from '@/features/gift-vouchers/types';

export type VoucherListProps = {
    batches: GiftVoucherBatch[];
    filters: { status: string | null; search: string | null };
};

export function useVoucherList(props: VoucherListProps) {
    const { t } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const dialog = useDialog();

    const filtering = ref(false);

    const filterStatus = ref(props.filters.status ?? '');

    const filterSearch = ref(props.filters.search ?? '');

    const statusOptions = computed(() => [
        { value: '', label: t('gift_vouchers.filters.all') },
        { value: 'active', label: t('gift_vouchers.status.active') },
        { value: 'expired', label: t('gift_vouchers.status.expired') },
        { value: 'redeemed', label: t('gift_vouchers.status.redeemed') },
        { value: 'voided', label: t('gift_vouchers.status.voided') },
    ]);

    function applyFilters(): void {
        router.get(
            route('gift-vouchers.index'),
            {
                status: filterStatus.value || undefined,
                search: filterSearch.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => {
                    filtering.value = true;
                },
                onFinish: () => {
                    filtering.value = false;
                },
            },
        );
    }

    function openPrint(url: string): void {
        const printWindow = window.open(url, '_blank');

        if (printWindow !== null) printWindow.opener = null;
    }

    async function voidVoucher(voucher: GiftVoucherRow): Promise<void> {
        const reason = await dialog.prompt({
            title: t('gift_vouchers.actions.void'),
            message: t('gift_vouchers.actions.void_help'),
            label: t('gift_vouchers.reason'),
            confirmLabel: t('gift_vouchers.actions.void'),
            required: true,
            maxLength: 500,
            variant: 'danger',
        });
        if (reason === null) return;
        router.post(
            route('gift-vouchers.void', voucher.id),
            { reason },
            withActionErrorToast(),
        );
    }

    async function reverseVoucher(voucher: GiftVoucherRow): Promise<void> {
        const reason = await dialog.prompt({
            title: t('gift_vouchers.actions.reverse'),
            message: t('gift_vouchers.actions.reverse_help'),
            label: t('gift_vouchers.reason'),
            confirmLabel: t('gift_vouchers.actions.reverse'),
            required: true,
            maxLength: 500,
            variant: 'warning',
        });
        if (reason === null) return;
        router.post(
            route('gift-vouchers.reverse-redemption', voucher.id),
            { reason },
            withActionErrorToast(),
        );
    }

    function statusVariant(
        status: GiftVoucherStatus,
    ): 'success' | 'neutral' | 'incoming' | 'danger' {
        return (
            {
                active: 'success',
                expired: 'neutral',
                redeemed: 'incoming',
                voided: 'danger',
            } as const
        )[status];
    }
    return {
        t,
        route,
        filtering,
        filterStatus,
        filterSearch,
        statusOptions,
        applyFilters,
        openPrint,
        voidVoucher,
        reverseVoucher,
        statusVariant,
    };
}
