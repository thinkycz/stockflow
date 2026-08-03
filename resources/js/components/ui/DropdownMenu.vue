<script setup lang="ts">
import { EllipsisVertical } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, useId } from 'vue';

type Placement =
    | 'bottom-end'
    | 'bottom-start'
    | 'top-end'
    | 'top-start'
    | 'right-start'
    | 'right-end'
    | 'left-start'
    | 'left-end';

const props = withDefaults(
    defineProps<{
        label: string;
        placement?: Placement;
        triggerClass?: string;
    }>(),
    {
        placement: 'bottom-end',
        triggerClass: '',
    },
);

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const menuId = `dropdown-${useId()}`;

const panelClass = computed<string>(() => {
    switch (props.placement) {
        case 'bottom-start':
            return 'top-full mt-1 left-0';
        case 'top-end':
            return 'bottom-full mb-1 right-0';
        case 'top-start':
            return 'bottom-full mb-1 left-0';
        case 'right-start':
            return 'left-full ml-2 top-0';
        case 'right-end':
            return 'left-full ml-2 bottom-0';
        case 'left-start':
            return 'right-full mr-2 top-0';
        case 'left-end':
            return 'right-full mr-2 bottom-0';
        case 'bottom-end':
        default:
            return 'top-full mt-1 right-0';
    }
});

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
    if (open.value) {
        if (event.key === 'Escape') {
            event.preventDefault();
            close(true);
            return;
        }
        const menuItems = items();
        const current = menuItems.indexOf(
            document.activeElement as HTMLElement,
        );
        let target = current;
        if (event.key === 'ArrowDown')
            target = (current + 1) % menuItems.length;
        else if (event.key === 'ArrowUp')
            target = (current - 1 + menuItems.length) % menuItems.length;
        else if (event.key === 'Home') target = 0;
        else if (event.key === 'End') target = menuItems.length - 1;
        else return;
        event.preventDefault();
        menuItems[target]?.focus();
    } else if (
        document.activeElement === trigger.value &&
        (event.key === 'Enter' ||
            event.key === ' ' ||
            event.key === 'ArrowDown')
    ) {
        event.preventDefault();
        toggle();
    }
}

onMounted(() =>
    document.addEventListener('pointerdown', onDocumentPointerDown),
);
onUnmounted(() =>
    document.removeEventListener('pointerdown', onDocumentPointerDown),
);
</script>

<template>
    <div ref="root" class="relative inline-flex" @keydown="onKeydown">
        <div
            ref="trigger"
            role="button"
            tabindex="0"
            :aria-label="label"
            :aria-expanded="open"
            :aria-controls="menuId"
            aria-haspopup="menu"
            class="cursor-pointer rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            :class="triggerClass"
            @click="toggle"
        >
            <slot name="trigger">
                <span
                    class="flex size-8 items-center justify-center text-on-surface-variant transition hover:bg-surface-container-low hover:text-on-surface"
                >
                    <EllipsisVertical :size="17" />
                </span>
            </slot>
        </div>
        <div
            v-if="open"
            :id="menuId"
            role="menu"
            :class="[
                'absolute z-50 min-w-48 max-w-xs overflow-hidden rounded-xl border border-outline-glass bg-white p-1 shadow-lg',
                panelClass,
            ]"
            @click="close()"
        >
            <slot />
        </div>
    </div>
</template>
