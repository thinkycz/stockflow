<script setup lang="ts">
import { EllipsisVertical } from '@lucide/vue';
import { nextTick, onMounted, onUnmounted, ref, useId } from 'vue';

defineProps<{ label: string }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const menuId = `dropdown-${useId()}`;

function items(): HTMLElement[] {
    return Array.from(
        root.value?.querySelectorAll<HTMLElement>(
            '[role="menuitem"]:not([disabled])',
        ) ?? [],
    );
}

function toggle(): void {
    open.value = !open.value;
    if (open.value) void nextTick(() => items()[0]?.focus());
}

function close(restoreFocus = false): void {
    open.value = false;
    if (restoreFocus) void nextTick(() => trigger.value?.focus());
}

function onDocumentPointerDown(event: PointerEvent): void {
    if (open.value && !root.value?.contains(event.target as Node)) close();
}

function onKeydown(event: KeyboardEvent): void {
    if (!open.value) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
        return;
    }
    const menuItems = items();
    const current = menuItems.indexOf(document.activeElement as HTMLElement);
    let target = current;
    if (event.key === 'ArrowDown') target = (current + 1) % menuItems.length;
    else if (event.key === 'ArrowUp')
        target = (current - 1 + menuItems.length) % menuItems.length;
    else if (event.key === 'Home') target = 0;
    else if (event.key === 'End') target = menuItems.length - 1;
    else return;
    event.preventDefault();
    menuItems[target]?.focus();
}

onMounted(() =>
    document.addEventListener('pointerdown', onDocumentPointerDown),
);
onUnmounted(() =>
    document.removeEventListener('pointerdown', onDocumentPointerDown),
);
</script>

<template>
    <div ref="root" class="relative" @keydown="onKeydown">
        <button
            ref="trigger"
            type="button"
            class="flex size-8 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low hover:text-on-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            :aria-label="label"
            :aria-expanded="open"
            :aria-controls="menuId"
            aria-haspopup="menu"
            @click="toggle"
        >
            <EllipsisVertical :size="17" />
        </button>
        <div
            v-if="open"
            :id="menuId"
            role="menu"
            class="absolute top-9 right-0 z-30 w-52 overflow-hidden rounded-xl border border-outline-glass bg-white p-1 shadow-lg"
            @click="close()"
        >
            <slot />
        </div>
    </div>
</template>
