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
    <header :class="cn('mb-4 flex flex-col gap-1', $props.class)">
        <slot />
    </header>
</template>
