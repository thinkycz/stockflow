<script setup lang="ts">
import { cn } from '@/lib/utils';

defineProps<{
    title: string;
    value: string | number;
    description?: string;
}>();
</script>

<template>
    <dl
        :class="
            cn(
                'rounded-2xl border border-outline-glass bg-surface-container-lowest p-4 shadow-sm',
            )
        "
    >
        <div class="flex items-start justify-between">
            <dt
                class="font-mono text-[10px] font-bold tracking-wider text-on-surface-variant uppercase"
            >
                {{ title }}
            </dt>
            <span aria-hidden="true" class="text-on-surface-variant">
                <slot name="icon" />
            </span>
        </div>
        <dd
            class="mt-1.5 font-heading text-xl font-bold tracking-tight text-on-surface"
        >
            {{ value }}
        </dd>
        <dd
            v-if="description || $slots.description"
            class="mt-1 text-xs font-medium text-on-surface-variant"
        >
            <slot name="description">{{ description }}</slot>
        </dd>
    </dl>
</template>
