<script setup lang="ts">
import { X } from '@lucide/vue';
import { onMounted, onUnmounted, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        class?: string;
    }>(),
    {
        title: '',
        class: '',
    },
);

const emit = defineEmits<{
    close: [];
}>();

let previousBodyOverflow = '';

function close(): void {
    emit('close');
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape' && props.open) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);

    if (props.open) {
        document.body.style.overflow = previousBodyOverflow;
    }
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            previousBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = previousBodyOverflow;
        }
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <div
                class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                @click="close"
            />
            <div
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
                            class="font-heading text-lg font-bold tracking-tight text-on-surface"
                        >
                            {{ title }}
                        </h2>
                    </slot>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-on-surface-variant transition hover:bg-surface-container-high hover:text-on-surface"
                        :aria-label="'Close'"
                        @click="close"
                    >
                        <X :size="16" />
                    </button>
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
