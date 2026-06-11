<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        class?: string;
        padded?: boolean;
    }>(),
    {
        class: '',
        padded: true,
    },
);
</script>

<template>
    <section
        :class="
            cn(
                'rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-sm',
                padded ? 'p-6' : '',
                $props.class,
            )
        "
    >
        <slot />
    </section>
</template>
