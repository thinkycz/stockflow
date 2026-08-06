<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const badgeVariants = cva(
    'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold leading-none tracking-wide uppercase transition-colors',
    {
        variants: {
            variant: {
                neutral: 'border-outline-glass bg-neutral-bg text-neutral',
                incoming: 'border-incoming/20 bg-incoming-bg text-incoming',
                outgoing: 'border-outgoing/20 bg-outgoing-bg text-outgoing',
                transfer: 'border-transfer/20 bg-transfer-bg text-transfer',
                consumption:
                    'border-consumption/20 bg-consumption-bg text-consumption',
                adjustment:
                    'border-adjustment/20 bg-adjustment-bg text-adjustment',
                inventory: 'border-inventory/20 bg-inventory-bg text-inventory',
                reversal: 'border-reversal/20 bg-reversal-bg text-reversal',
                success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
                warning: 'border-amber-200 bg-amber-50 text-amber-700',
                danger: 'border-red-200 bg-red-50 text-red-700',
            },
        },
        defaultVariants: {
            variant: 'neutral',
        },
    },
);

type BadgeVariants = VariantProps<typeof badgeVariants>;

withDefaults(
    defineProps<{
        variant?: BadgeVariants['variant'];
        class?: string;
    }>(),
    {
        variant: 'neutral',
        class: '',
    },
);
</script>

<template>
    <span :class="cn(badgeVariants({ variant }), $attrs.class as string)">
        <slot />
    </span>
</template>
