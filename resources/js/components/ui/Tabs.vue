<script setup lang="ts">
import type { Component } from 'vue';
import Button from '@/components/ui/Button.vue';
import { cn } from '@/lib/utils';

export type TabItem = {
    value: string;
    label: string;
    icon?: Component;
    disabled?: boolean;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        items: TabItem[];
        label: string;
        variant?: 'segmented' | 'underline';
        class?: string;
    }>(),
    {
        variant: 'segmented',
        class: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function select(item: TabItem, target?: HTMLElement): void {
    if (item.disabled === true) return;
    emit('update:modelValue', item.value);
    target?.focus();
}

function onKeydown(event: KeyboardEvent, item: TabItem): void {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

    const enabled = props.items.filter(
        (candidate) => candidate.disabled !== true,
    );
    const current = enabled.findIndex(
        (candidate) => candidate.value === item.value,
    );
    if (current === -1 || enabled.length === 0) return;

    event.preventDefault();
    let next = current;
    if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = enabled.length - 1;
    else if (event.key === 'ArrowRight') next = (current + 1) % enabled.length;
    else next = (current - 1 + enabled.length) % enabled.length;

    const buttons = Array.from(
        (
            event.currentTarget as HTMLElement
        ).parentElement?.querySelectorAll<HTMLElement>(
            '[role="tab"]:not([disabled])',
        ) ?? [],
    );
    select(enabled[next]!, buttons[next]);
}
</script>

<template>
    <div
        role="tablist"
        aria-orientation="horizontal"
        :aria-label="label"
        :class="
            cn(
                variant === 'segmented'
                    ? 'flex w-fit max-w-full gap-1 overflow-x-auto rounded-xl border border-outline-glass bg-surface-container-low p-1'
                    : 'flex gap-6 overflow-x-auto border-b border-outline-glass',
                $props.class,
            )
        "
    >
        <Button
            v-for="item in items"
            :key="item.value"
            type="button"
            role="tab"
            variant="ghost"
            size="compact"
            :disabled="item.disabled"
            :aria-selected="modelValue === item.value"
            :tabindex="modelValue === item.value ? 0 : -1"
            :class="
                cn(
                    'min-w-fit shrink-0 px-4 text-sm',
                    variant === 'segmented'
                        ? modelValue === item.value
                            ? 'rounded-lg bg-white text-primary shadow-sm hover:bg-white'
                            : 'rounded-lg text-on-surface-variant'
                        : modelValue === item.value
                          ? 'relative h-11 rounded-none border-0 bg-transparent px-1 text-primary shadow-none after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:rounded-full after:bg-primary hover:bg-transparent'
                          : 'relative h-11 rounded-none border-0 bg-transparent px-1 text-on-surface-variant shadow-none after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:rounded-full after:bg-transparent hover:bg-transparent hover:text-on-surface',
                )
            "
            @click="select(item, $event.currentTarget as HTMLElement)"
            @keydown="onKeydown($event, item)"
        >
            <component :is="item.icon" v-if="item.icon" :size="15" />
            {{ item.label }}
        </Button>
    </div>
</template>
