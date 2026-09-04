import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import type {
    GiftVoucherLookup,
    GiftVoucherStatus,
} from '@/features/gift-vouchers/types';

export type VoucherRedemptionProps = {
    is_admin: boolean;
    can_redeem: boolean;
    lookup: GiftVoucherLookup | null;
};

export function useVoucherRedemption(props: VoucherRedemptionProps) {
    const { t } = useI18n();

    useBoundLocale();

    const route = useRoute();

    const lookupForm = useForm({ code: '' });

    const redeemForm = useForm({ ticket: '' });

    function submitLookup(): void {
        lookupForm.post(route('gift-vouchers.lookup'), {
            preserveScroll: true,
            onSuccess: () => lookupForm.reset(),
        });
    }

    function redeem(): void {
        if (props.lookup === null) return;
        redeemForm.ticket = props.lookup.ticket;
        redeemForm.post(
            route('gift-vouchers.redeem', props.lookup.voucher_id),
            {
                preserveScroll: true,
            },
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
        lookupForm,
        redeemForm,
        submitLookup,
        redeem,
        statusVariant,
    };
}
