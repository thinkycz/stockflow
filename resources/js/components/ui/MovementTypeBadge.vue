<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import { useI18n } from 'vue-i18n';

type MovementType = 'incoming' | 'outgoing' | 'adjustment';
type LabelKey = 'incoming' | 'outgoing' | 'transfer' | 'adjustment';

const props = defineProps<{
    type: MovementType;
    labelKey?: LabelKey;
}>();

const { t } = useI18n();

const resolvedLabelKey = computed<LabelKey>(
    () => props.labelKey ?? (props.type as LabelKey),
);

const variant = computed<'incoming' | 'outgoing' | 'adjustment'>(() => {
    if (resolvedLabelKey.value === 'transfer') {
        return 'outgoing';
    }

    return props.type;
});

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
