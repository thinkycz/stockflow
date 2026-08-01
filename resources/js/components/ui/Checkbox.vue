<script setup lang="ts">
import { cn } from '@/lib/utils';

const model = defineModel<boolean>({ default: false });

withDefaults(
    defineProps<{
        id?: string;
        disabled?: boolean;
        class?: string;
    }>(),
    { id: undefined, disabled: false, class: '' },
);
</script>

<template>
    <input
        :id="$props.id"
        v-model="model"
        type="checkbox"
        :disabled="$props.disabled"
        :class="
            cn(
                'size-4 rounded border-outline-glass text-primary focus-visible:ring-2 focus-visible:ring-primary/30 disabled:cursor-default disabled:opacity-60',
                $props.class,
            )
        "
    />
</template>
