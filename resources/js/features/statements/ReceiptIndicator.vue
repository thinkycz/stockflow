<script setup lang="ts">
import PayoutEstimate from '@/components/PayoutEstimate.vue';
import MarketplaceFeeBreakdown from '@/components/MarketplaceFeeBreakdown.vue';

import { Link } from '@inertiajs/vue3';
import { CircleCheck, CircleAlert, Clock3 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { useRoute } from '@/composables/useRoute';
import {
    formatCzechDate,
    formatCzechDateRange,
} from '@/composables/useCzechDate';
import { formatMoney } from '@/lib/format';
import type { Receipt } from './receipt-status';

const props = defineProps<{ receipts: Receipt[]; pending: boolean }>();
const { t } = useI18n();
const route = useRoute();
const open = ref(false);
const state = computed(() =>
    props.pending
        ? 'pending'
        : props.receipts.every((receipt) => receipt.state === 'verified')
          ? 'verified'
          : 'review',
);
const label = computed(() => t(`statements.receipts.${state.value}`));
function money(value: string | null): string {
    return value === null ? '—' : formatMoney(Number(value));
}
</script>

<template>
    <Button
        v-if="receipts.length"
        variant="ghost"
        size="icon-sm"
        class="shrink-0"
        :class="
            state === 'verified'
                ? 'text-emerald-700'
                : state === 'review'
                  ? 'text-amber-700'
                  : 'text-on-surface-variant'
        "
        :aria-label="label"
        :title="label"
        :data-receipt-state="state"
        @click="open = true"
    >
        <CircleCheck
            v-if="state === 'verified'"
            :size="14"
            aria-hidden="true"
        />
        <CircleAlert
            v-else-if="state === 'review'"
            :size="14"
            aria-hidden="true"
        />
        <Clock3 v-else :size="14" aria-hidden="true" />
    </Button>
    <Modal
        :open="open"
        :title="label"
        size="sm"
        body-class="max-h-[75dvh] overflow-y-auto"
        @close="open = false"
    >
        <p class="mb-4 text-sm text-on-surface-variant">
            {{ t('statements.receipts.period_scope') }}
        </p>
        <p v-if="pending" class="mb-4 text-sm">
            {{ t('statements.receipts.pending_description') }}
        </p>
        <div
            v-for="receipt in receipts"
            :key="receipt.transaction_id"
            class="space-y-2 border-t border-outline-glass py-3 text-sm text-left"
        >
            <p>
                {{ t('statements.receipts.received_on') }}:
                {{ formatCzechDate(receipt.booked_on) }}
            </p>
            <p>
                {{ t('statements.receipts.period') }}:
                {{ formatCzechDateRange(receipt.from, receipt.to) }}
            </p>
            <p>
                {{ t('statements.receipts.actual') }}:
                {{ money(receipt.check.actual) }}
            </p>
            <div>
                {{ t('bank_statements.transaction.expected') }}:
                <PayoutEstimate
                    :expected="money(receipt.check.expected)"
                    :fees="receipt.check.fees"
                    :range="receipt.check.range"
                />
            </div>
            <p v-if="!receipt.check.range">
                {{ t('statements.receipts.difference') }}:
                {{ money(receipt.check.difference) }}
            </p>
            <p v-if="!receipt.check.range">
                {{ t('statements.receipts.tolerance') }}:
                {{ money(receipt.check.tolerance) }}
            </p>
            <p v-if="receipt.check.reason">
                {{ t(`bank_statements.reasons.${receipt.check.reason}`) }}
            </p>
            <p v-if="receipt.channel === 'wolt' && !pending">
                {{ t('marketplace_fees.receipt_estimate') }}
            </p>
            <MarketplaceFeeBreakdown
                inline
                v-if="!pending"
                :fees="receipt.check.fees"
            />
            <Link
                :href="
                    route('bank-statements.show', {
                        bankStatement: receipt.statement_id,
                    })
                "
                class="inline-block text-primary underline"
                >{{ t('statements.bank_control.action') }}</Link
            >
        </div>
    </Modal>
</template>
