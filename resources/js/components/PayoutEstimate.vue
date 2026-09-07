<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatMoney } from '@/lib/format';
import type {
    MarketplaceFees,
    EstimateComparison,
} from '@/types/marketplace-fees';
defineProps<{
    fees?: MarketplaceFees | null;
    expected?: string | null;
    range?: EstimateComparison | null;
}>();
const { t } = useI18n();
const money = formatMoney;
</script>
<template>
    <div
        v-if="fees?.estimate_type === 'range'"
        class="space-y-1"
        data-payout-range
    >
        <span
            >{{ money(fees.transfer_min) }} –
            {{ money(fees.transfer_max) }}</span
        >
        <template v-if="range">
            <p class="text-xs">
                {{ t('marketplace_fees.lower_boundary') }}: Δ
                {{ money(range.difference_min) }}, ±
                {{ money(range.tolerance_min) }}
            </p>
            <p class="text-xs">
                {{ t('marketplace_fees.upper_boundary') }}: Δ
                {{ money(range.difference_max) }}, ±
                {{ money(range.tolerance_max) }}
            </p>
        </template>
    </div>
    <span v-else>{{ money(expected) }}</span>
</template>
