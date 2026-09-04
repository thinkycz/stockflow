<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Ban, CheckCircle2, Search, TicketCheck } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import BackLink from '@/components/ui/BackLink.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney } from '@/lib/format';
import type {
    GiftVoucherLookup,
    GiftVoucherStatus,
} from '@/types/gift-vouchers';

const props = defineProps<{
    is_admin: boolean;
    can_redeem: boolean;
    lookup: GiftVoucherLookup | null;
}>();

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
    redeemForm.post(route('gift-vouchers.redeem', props.lookup.voucher_id), {
        preserveScroll: true,
    });
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
</script>
<template>
    <AppLayout :title="t('gift_vouchers.redeem.title')">
        <div class="flex flex-col gap-6">
            <BackLink v-if="is_admin" :href="route('gift-vouchers.index')">{{
                t('gift_vouchers.back_to_overview')
            }}</BackLink>
            <PageHeader :title="t('gift_vouchers.redeem.title')" />
            <section class="mx-auto w-full max-w-2xl">
                <Card class="overflow-hidden p-0">
                    <div
                        class="border-b border-outline-glass px-6 py-5 sm:px-8"
                    >
                        <p
                            class="font-mono text-[10px] font-semibold tracking-[0.2em] text-on-surface-variant uppercase"
                        >
                            {{ t('gift_vouchers.redeem.eyebrow') }}
                        </p>
                        <h2 class="mt-2 font-heading text-2xl font-bold">
                            {{ t('gift_vouchers.redeem.title') }}
                        </h2>
                        <p
                            class="mt-2 max-w-lg text-sm text-on-surface-variant"
                        >
                            {{ t('gift_vouchers.redeem.description') }}
                        </p>
                    </div>

                    <div class="space-y-5 p-6 sm:p-8">
                        <div
                            v-if="!can_redeem"
                            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-medium text-amber-900"
                        >
                            {{ t('gift_vouchers.redeem.retail_required') }}
                        </div>

                        <form class="space-y-3" @submit.prevent="submitLookup">
                            <Label for="voucher_code" :required="true">
                                {{ t('gift_vouchers.code') }}
                            </Label>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    id="voucher_code"
                                    v-model="lookupForm.code"
                                    class="font-mono uppercase tracking-[0.16em]"
                                    autocomplete="off"
                                    :placeholder="
                                        t(
                                            'gift_vouchers.redeem.code_placeholder',
                                        )
                                    "
                                    :invalid="Boolean(lookupForm.errors.code)"
                                    required
                                />
                                <Button
                                    type="submit"
                                    :disabled="
                                        lookupForm.processing || !can_redeem
                                    "
                                >
                                    <Search :size="15" />
                                    {{ t('gift_vouchers.redeem.check') }}
                                </Button>
                            </div>
                            <FieldError :message="lookupForm.errors.code" />
                        </form>

                        <div
                            v-if="lookup"
                            class="rounded-2xl border border-outline-glass bg-surface-container-low p-5"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <CheckCircle2
                                            v-if="lookup.status === 'active'"
                                            :size="18"
                                            class="text-emerald-600"
                                        />
                                        <Ban
                                            v-else
                                            :size="18"
                                            class="text-error-red"
                                        />
                                        <Badge
                                            :variant="
                                                statusVariant(lookup.status)
                                            "
                                        >
                                            {{
                                                t(
                                                    'gift_vouchers.status.' +
                                                        lookup.status,
                                                )
                                            }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="mt-3 font-heading text-3xl font-bold text-on-surface"
                                    >
                                        {{ formatMoney(lookup.amount) }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-on-surface-variant"
                                    >
                                        •••• {{ lookup.code_suffix }}
                                        <template v-if="lookup.expires_at">
                                            ·
                                            {{
                                                t('gift_vouchers.valid_until', {
                                                    date: formatDate(
                                                        lookup.expires_at,
                                                    ),
                                                })
                                            }}
                                        </template>
                                    </p>
                                </div>
                                <Button
                                    variant="success"
                                    :disabled="
                                        lookup.status !== 'active' ||
                                        redeemForm.processing
                                    "
                                    @click="redeem"
                                >
                                    <TicketCheck :size="16" />
                                    {{ t('gift_vouchers.redeem.confirm') }}
                                </Button>
                            </div>
                            <FieldError :message="redeemForm.errors.ticket" />
                        </div>
                    </div>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
