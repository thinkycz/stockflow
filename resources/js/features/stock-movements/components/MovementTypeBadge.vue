<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import { useI18n } from 'vue-i18n';
import type {
    MovementDisplayLabelKey,
    MovementType,
} from '@/features/stock-movements/movement-display';

const props = defineProps<{
    type: MovementType;
    labelKey?: MovementDisplayLabelKey;
}>();

const { t } = useI18n();

const resolvedLabelKey = computed<MovementDisplayLabelKey>(
    () => props.labelKey ?? props.type,
);

const variant = computed<
    | 'incoming'
    | 'outgoing'
    | 'transfer'
    | 'consumption'
    | 'adjustment'
    | 'inventory'
    | 'reversal'
>(() =>
    resolvedLabelKey.value === 'inventory_reconciliation'
        ? 'inventory'
        : resolvedLabelKey.value,
);

const label = computed<string>(() =>
    t(`stock_movements.types.${resolvedLabelKey.value}`),
);
</script>

<template>
    <Badge :variant="variant">
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
        {{ label }}
    </Badge>
</template>
