<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        variant?:
            | 'primary'
            | 'secondary'
            | 'success'
            | 'warning'
            | 'ghost'
            | 'danger';
        class?: string;
        disabled?: boolean;
    }>(),
    {
        type: 'button',
        variant: 'primary',
        class: '',
        disabled: false,
    },
);

const variants = {
    primary:
        'border-primary/20 bg-gradient-to-b from-primary-container to-primary text-white shadow-[0_4px_12px_rgba(0,104,95,0.15)] hover:brightness-105 active:scale-[0.98]',
    secondary:
        'border-outline-glass bg-white text-on-surface hover:bg-surface-container-low',
    success:
        'border-emerald-600/30 bg-emerald-600 text-white shadow-[0_4px_12px_rgba(5,150,105,0.2)] hover:bg-emerald-500 active:scale-[0.98]',
    warning:
        'border-amber-500/40 bg-amber-400 text-amber-950 shadow-[0_4px_12px_rgba(245,158,11,0.22)] hover:bg-amber-300 active:scale-[0.98]',
    ghost: 'border-transparent bg-transparent text-on-surface-variant hover:bg-surface-container-low hover:text-primary',
    danger: 'border-error-red/20 bg-error-red text-white hover:brightness-105 active:scale-[0.98]',
};
</script>

<template>
    <button
        :type="props.type"
        :disabled="props.disabled"
        :class="
            cn(
                'inline-flex h-10 items-center justify-center rounded-xl border px-4 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer gap-2',
                variants[props.variant],
                props.class,
            )
        "
    >
        <slot />
    </button>
</template>
