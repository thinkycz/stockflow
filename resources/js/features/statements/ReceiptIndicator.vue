<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck, CircleAlert, Clock3 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { useRoute } from '@/composables/useRoute';
import { formatCzechDate } from '@/composables/useCzechDate';
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
        v-if="open"
        :open="open"
        :title="label"
        size="sm"
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
                {{ formatCzechDate(receipt.from) }} –
                {{ formatCzechDate(receipt.to) }}
            </p>
            <p>
                {{ t('statements.receipts.actual') }}:
                {{ money(receipt.check.actual) }}
            </p>
            <p>
                {{ t('bank_statements.transaction.expected') }}:
                {{ money(receipt.check.expected) }}
            </p>
            <p>
                {{ t('statements.receipts.difference') }}:
                {{ money(receipt.check.difference) }}
            </p>
            <p>
                {{ t('statements.receipts.tolerance') }}:
                {{ money(receipt.check.tolerance) }}
            </p>
            <p v-if="receipt.check.reason">
                {{ t(`bank_statements.reasons.${receipt.check.reason}`) }}
            </p>
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
