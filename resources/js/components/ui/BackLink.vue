<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

defineProps<{ href: string }>();
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant transition hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
    >
        <ArrowLeft :size="12" aria-hidden="true" />
        <slot />
    </Link>
</template>
