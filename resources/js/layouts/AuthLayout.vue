<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Brand from '@/components/ui/Brand.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';

defineProps<{
    title: string;
    subtitle: string;
}>();
</script>

<template>
    <Head :title="title" />

    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-surface-bg p-6 font-sans antialiased md:p-10"
    >
        <div class="w-full max-w-md">
            <!-- Glassmorphic Card Container -->
            <div
                class="space-y-6 rounded-2xl border border-outline-glass bg-surface-container-lowest p-8 shadow-xl md:p-10"
            >
                <!-- Brand Header -->
                <div class="flex flex-col items-center gap-4 text-center">
                    <Brand href="/login" />

                    <div class="mt-2 space-y-1.5">
                        <h2
                            class="font-heading text-lg leading-tight font-black text-on-surface"
                        >
                            {{ title }}
                        </h2>
                        <p
                            class="text-center text-xs leading-relaxed text-on-surface-variant"
                        >
                            {{ subtitle }}
                        </p>
                    </div>
                </div>

                <FlashAlerts />

                <!-- Form Slot -->
                <slot />
            </div>
        </div>
    </div>
</template>
