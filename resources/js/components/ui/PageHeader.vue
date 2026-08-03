<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        class?: string;
    }>(),
    {
        subtitle: '',
        class: '',
    },
);
</script>

<template>
    <header
        :class="
            cn(
                'flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between',
                $props.class,
            )
        "
    >
        <div class="min-w-0">
            <slot name="before" />
            <h1
                class="font-heading text-2xl font-bold tracking-tight text-on-surface"
            >
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-1 text-sm text-on-surface-variant">
                {{ subtitle }}
            </p>
            <slot name="context" />
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
