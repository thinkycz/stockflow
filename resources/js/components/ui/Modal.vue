<script setup lang="ts">
import { X } from '@lucide/vue';
import { nextTick, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import { cn } from '@/lib/utils';

let bodyLockCount = 0;
let originalBodyOverflow = '';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        class?: string;
        closeOnBackdrop?: boolean;
        layer?: 'default' | 'alert';
    }>(),
    {
        title: '',
        class: '',
        closeOnBackdrop: true,
        layer: 'default',
    },
);

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
const panel = ref<HTMLElement | null>(null);
const titleId = `modal-title-${useId()}`;
let previousFocus: HTMLElement | null = null;
let hasBodyLock = false;

function lockBody(): void {
    if (hasBodyLock) return;
    if (bodyLockCount === 0) {
        originalBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    }
    bodyLockCount += 1;
    hasBodyLock = true;
}

function unlockBody(): void {
    if (!hasBodyLock) return;
    bodyLockCount = Math.max(0, bodyLockCount - 1);
    hasBodyLock = false;
    if (bodyLockCount === 0)
        document.body.style.overflow = originalBodyOverflow;
}

function focusable(): HTMLElement[] {
    if (panel.value === null) return [];
    return Array.from(
        panel.value.querySelectorAll<HTMLElement>(
            'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])',
        ),
    ).filter((element) => !element.hasAttribute('hidden'));
}

function close(): void {
    emit('close');
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape' && props.open) {
        close();
        return;
    }
    if (e.key === 'Tab' && props.open) {
        const elements = focusable();
        if (elements.length === 0) {
            e.preventDefault();
            panel.value?.focus();
            return;
        }
        const first = elements[0];
        const last = elements[elements.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last?.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first?.focus();
        }
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);

    unlockBody();
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            previousFocus =
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null;
            lockBody();
            void nextTick(() => {
                const autofocus =
                    panel.value?.querySelector<HTMLElement>('[autofocus]');
                (autofocus ?? focusable()[0] ?? panel.value)?.focus();
            });
        } else {
            unlockBody();
            previousFocus?.focus();
            previousFocus = null;
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            :class="[
                'fixed inset-0 flex items-center justify-center p-4',
                layer === 'alert' ? 'z-[60]' : 'z-50',
            ]"
        >
            <div
                class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                @click="closeOnBackdrop ? close() : undefined"
            />
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="title ? titleId : undefined"
                tabindex="-1"
                :class="
                    cn(
                        'relative z-10 w-full max-w-lg rounded-2xl border border-outline-glass bg-surface-container-lowest shadow-xl',
                        $props.class,
                    )
                "
            >
                <div
                    v-if="title || $slots.header"
                    class="flex items-center justify-between border-b border-outline-glass px-6 py-4"
                >
                    <slot name="header">
                        <h2
                            :id="titleId"
                            class="font-heading text-lg font-bold tracking-tight text-on-surface"
                        >
                            {{ title }}
                        </h2>
                    </slot>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8 rounded-lg"
                        :aria-label="t('nav.close')"
                        @click="close"
                    >
                        <X :size="16" />
                    </Button>
                </div>
                <div class="px-6 py-4">
                    <slot />
                </div>
                <div
                    v-if="$slots.footer"
                    class="flex items-center justify-end gap-3 border-t border-outline-glass px-6 py-4"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
