<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import Label from '@/components/ui/Label.vue';

withDefaults(
    defineProps<{
        for: string;
        label: string;
        busy?: boolean;
        class?: string;
    }>(),
    { busy: false, class: '' },
);
</script>

<template>
    <div :class="['flex flex-col gap-1', $props.class]" :aria-busy="busy">
        <div class="flex min-h-3 items-center justify-between gap-2">
            <Label :for="$props.for">{{ label }}</Label>
            <LoaderCircle
                v-if="busy"
                :size="12"
                class="animate-spin text-primary"
                aria-hidden="true"
            />
        </div>
        <slot />
    </div>
</template>
