<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatMoney, formatNumber } from '@/lib/format';
import type { MarketplaceFees } from '@/types/marketplace-fees';

defineProps<{ fees?: MarketplaceFees | null }>();
const { t } = useI18n();
const fields = [
    'base',
    'commission',
    'vat',
    'deduction',
    'net_revenue',
    'expected_transfer',
] as const;
</script>

<template>
    <details
        v-if="fees"
        class="mt-2 text-left text-xs text-on-surface-variant"
        data-marketplace-fees
    >
        <summary class="cursor-pointer font-medium text-on-surface">
            {{ t('marketplace_fees.title') }}
        </summary>
        <dl class="mt-2 space-y-2">
            <div
                v-for="field in fields"
                :key="field"
                class="flex flex-wrap justify-between gap-x-3 gap-y-1"
            >
                <dt>
                    {{ t(`marketplace_fees.${field}`)
                    }}<template v-if="field === 'commission'">
                        ({{
                            formatNumber(Number(fees.commission_rate) * 100)
                        }}
                        %)</template
                    ><template v-if="field === 'vat'">
                        ({{
                            formatNumber(Number(fees.vat_rate) * 100)
                        }}
                        %)</template
                    >
                </dt>
                <dd class="font-medium tabular-nums">
                    {{ formatMoney(Number(fees[field])) }}
                </dd>
            </div>
        </dl>
        <p class="mt-3 max-w-md">{{ t('marketplace_fees.estimate') }}</p>
    </details>
</template>
