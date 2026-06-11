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
    <p :class="cn('text-xs font-medium text-on-surface-variant', $props.class)">
        <slot />
    </p>
</template>
