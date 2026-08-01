<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Printer } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';

defineProps<{
    detailedHref: string;
    simpleHref: string;
}>();

const { t } = useI18n();
const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<InstanceType<typeof Button> | null>(null);

function close(): void {
    open.value = false;
}

function handlePointerDown(event: PointerEvent): void {
    if (!root.value?.contains(event.target as Node)) close();
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || !open.value) return;
    close();
    (trigger.value?.$el as HTMLButtonElement | undefined)?.focus();
}

onMounted(() => {
    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handlePointerDown);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div ref="root" class="relative inline-flex">
        <Button
            ref="trigger"
            variant="secondary"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="open = !open"
        >
            <Printer :size="15" />
            {{ t('payroll.print') }}
            <ChevronDown :size="14" aria-hidden="true" />
        </Button>
        <div
            v-if="open"
            role="menu"
            class="absolute top-full right-0 z-30 mt-2 min-w-52 rounded-xl border border-outline-glass bg-white p-1.5 shadow-lg"
        >
            <Link
                :href="simpleHref"
                target="_blank"
                role="menuitem"
                class="block rounded-lg px-3 py-2 text-sm font-medium text-on-surface hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                @click="close"
            >
                {{ t('payroll.print_simple') }}
            </Link>
            <Link
                :href="detailedHref"
                target="_blank"
                role="menuitem"
                class="block rounded-lg px-3 py-2 text-sm font-medium text-on-surface hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
                @click="close"
            >
                {{ t('payroll.print_detailed') }}
            </Link>
        </div>
    </div>
</template>
