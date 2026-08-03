<script setup lang="ts">
import { Loader2 } from '@lucide/vue';
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
        size?: 'default' | 'compact' | 'icon' | 'icon-sm';
        class?: string;
        disabled?: boolean;
        loading?: boolean;
        loadingLabel?: string;
    }>(),
    {
        type: 'button',
        variant: 'primary',
        size: 'default',
        class: '',
        disabled: false,
        loading: false,
        loadingLabel: '',
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

const sizes = {
    default: 'h-10 gap-2 px-4',
    compact: 'h-8 gap-1.5 px-2.5',
    icon: 'size-10 p-0',
    'icon-sm': 'size-8 p-0',
};
</script>

<template>
    <button
        :type="props.type"
        :disabled="props.disabled || props.loading"
        :aria-busy="props.loading || undefined"
        :class="
            cn(
                'inline-flex cursor-pointer items-center justify-center rounded-xl border text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                variants[props.variant],
                sizes[props.size],
                props.class,
            )
        "
    >
        <Loader2 v-if="props.loading" :size="14" class="animate-spin" />
        <span v-if="props.loadingLabel" class="sr-only">
            {{ props.loadingLabel }}
        </span>
        <slot />
    </button>
</template>
