<script setup lang="ts">
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { useI18n } from 'vue-i18n';
import { formatMoney, formatNumber } from '@/lib/format';
import type { MarketplaceFees } from '@/types/marketplace-fees';

defineProps<{ fees?: MarketplaceFees | null; inline?: boolean }>();
const open = ref(false);
const { t } = useI18n();
const fields = [
    'base',
    'commission',
    'vat',
    'transaction_fee',
    'transaction_vat',
    'deduction',
    'net_revenue',
    'expected_transfer',
] as const;
</script>

<template>
    <p
        v-if="!inline && fees?.estimate_type === 'range'"
        class="text-xs text-on-surface-variant"
        data-estimate-summary
    >
        {{ t('marketplace_fees.range') }}:
        {{ formatMoney(Number(fees.net_min)) }} –
        {{ formatMoney(Number(fees.net_max)) }}
    </p>
    <Button
        v-if="fees && !inline"
        variant="ghost"
        size="compact"
        @click="open = true"
        >{{ t('marketplace_fees.title') }}</Button
    >
    <component
        :is="inline ? 'div' : Modal"
        v-if="fees"
        :open="open"
        :title="t('marketplace_fees.title')"
        body-class="max-h-[75dvh] overflow-y-auto"
        @close="open = false"
    >
        <div
            v-if="fees?.estimate_type === 'range'"
            class="mt-2 text-xs text-on-surface-variant"
            data-estimate-range
        >
            <p>
                {{ t('marketplace_fees.range') }}:
                {{ formatMoney(Number(fees.net_min)) }} –
                {{ formatMoney(Number(fees.net_max)) }}
            </p>
            <p>{{ t('marketplace_fees.conservative') }}</p>
            <p>{{ t('marketplace_fees.range_limits') }}</p>
        </div>
        <p
            v-else-if="fees?.excluded_items?.includes('other_adjustments')"
            class="mt-2 text-xs text-on-surface-variant"
        >
            {{ t('marketplace_fees.bolt_limits') }}
        </p>
        <div
            v-if="fees"
            class="mt-2 text-left text-xs text-on-surface-variant"
            data-marketplace-fees
        >
            <h3 class="font-medium text-on-surface">
                {{ t('marketplace_fees.title') }}
            </h3>
            <dl class="mt-2 space-y-2">
                <div
                    v-for="field in fields"
                    :key="field"
                    :data-fee-field="field"
                    class="flex flex-wrap justify-between gap-x-3 gap-y-1"
                >
                    <dt>
                        {{ t(`marketplace_fees.${field}`)
                        }}<template v-if="field === 'commission'">
                            ({{
                                formatNumber(Number(fees.commission_rate) * 100)
                            }}
                            %)</template
                        ><template
                            v-if="
                                field === 'vat' &&
                                (fees.segments?.length ?? 1) === 1
                            "
                        >
                            ({{
                                formatNumber(Number(fees.vat_rate) * 100)
                            }}
                            %)</template
                        >
                    </dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatMoney(Number(fees[field] ?? '0')) }}
                    </dd>
                </div>
            </dl>
            <div v-if="fees.segments && fees.segments.length > 1" class="mt-3">
                <p>{{ t('marketplace_fees.tax_regimes') }}</p>
                <p v-for="segment in fees.segments" :key="segment.vat_rate">
                    {{ formatNumber(Number(segment.vat_rate) * 100) }} %:
                    {{ t('marketplace_fees.base') }}
                    {{ formatMoney(Number(segment.base)) }},
                    {{ t('marketplace_fees.commission') }}
                    {{ formatMoney(Number(segment.commission)) }},
                    {{ t('marketplace_fees.vat') }}
                    {{ formatMoney(Number(segment.vat)) }}
                </p>
            </div>
            <p class="mt-3 max-w-md">{{ t('marketplace_fees.estimate') }}</p>
        </div>
    </component>
</template>
