<script setup lang="ts">
import { Loader2 } from '@lucide/vue';

withDefaults(
    defineProps<{
        label?: string;
        inline?: boolean;
    }>(),
    {
        label: '',
        inline: false,
    },
);
</script>

<template>
    <div
        v-if="inline"
        class="inline-flex items-center gap-2 text-xs font-medium text-on-surface-variant"
    >
        <Loader2 :size="14" class="animate-spin" />
        <span v-if="label">{{ label }}</span>
    </div>
    <div
        v-else
        class="flex flex-col items-center justify-center gap-2 py-10 text-on-surface-variant"
    >
        <Loader2 :size="20" class="animate-spin" />
        <p v-if="label" class="text-xs font-medium">{{ label }}</p>
    </div>
</template>
