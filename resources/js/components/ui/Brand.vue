<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from '@/composables/useRoute';
import { useSharedProps } from '@/composables/useSharedProps';

const props = withDefaults(
    defineProps<{
        href?: string;
        class?: string;
    }>(),
    {
        href: undefined,
        class: '',
    },
);

const route = useRoute();
const { app } = useSharedProps();

const resolvedHref = computed(() => props.href ?? route('dashboard'));
</script>

<template>
    <Link
        :href="resolvedHref"
        :class="[
            'flex items-center gap-3 font-medium select-none',
            $props.class,
        ]"
    >
        <div
            class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary font-heading text-base font-bold text-white shadow-[0_2px_8px_rgba(15,23,42,0.2)]"
        >
            S
        </div>
        <div class="text-left">
            <h1
                class="mb-0.5 font-heading text-sm font-bold tracking-tight text-on-surface leading-none"
            >
                {{ app.name }}
            </h1>
            <p
                class="font-mono text-[9px] font-semibold tracking-wider text-on-surface-variant uppercase opacity-75 leading-none"
            >
                Inventory Management
            </p>
        </div>
    </Link>
</template>
