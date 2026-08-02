<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        href?: string;
        tone?: 'default' | 'danger';
        disabled?: boolean;
        class?: string;
    }>(),
    { href: undefined, tone: 'default', disabled: false, class: '' },
);

const emit = defineEmits<{ select: [] }>();

const itemClass = computed(() =>
    cn(
        'flex min-h-9 w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-normal transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 disabled:pointer-events-none disabled:opacity-50',
        props.tone === 'danger'
            ? 'text-error-red hover:bg-error-red/10'
            : 'text-on-surface hover:bg-surface-container-low',
        props.class,
    ),
);
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        role="menuitem"
        :class="itemClass"
        @click="emit('select')"
    >
        <slot />
    </Link>
    <button
        v-else
        type="button"
        role="menuitem"
        :disabled="disabled"
        :class="itemClass"
        @click="emit('select')"
    >
        <slot />
    </button>
</template>
