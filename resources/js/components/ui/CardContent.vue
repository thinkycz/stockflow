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
    <div :class="cn('text-on-surface', $props.class)">
        <slot />
    </div>
</template>
