<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        class?: string;
    }>(),
    {
        class: '',
    },
);
</script>

<template>
    <h3
        :class="
            cn(
                'font-heading text-sm font-semibold tracking-tight text-on-surface',
                $props.class,
            )
        "
    >
        <slot />
    </h3>
</template>
