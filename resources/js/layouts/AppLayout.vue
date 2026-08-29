<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppSidebar from '@/components/ui/AppSidebar.vue';
import Brand from '@/components/ui/Brand.vue';
import FlashToasts from '@/components/ui/FlashToasts.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useRoute } from '@/composables/useRoute';

const props = withDefaults(
    defineProps<{
        title: string;
        fullBleed?: boolean;
    }>(),
    {
        fullBleed: false,
    },
);

const { t } = useI18n();

useBoundLocale();

const route = useRoute();
const mobileNavOpen = ref(false);

function closeMobileNav(): void {
    mobileNavOpen.value = false;
}

// Close the drawer only when navigation changes the URL. A same-page store
// refresh keeps it open; removing a stale store query override closes it.
let lastUrl: string = typeof window !== 'undefined' ? window.location.href : '';

const navigateHandler = (): void => {
    const currentUrl = window.location.href;
    if (currentUrl !== lastUrl) {
        closeMobileNav();
    }
    lastUrl = currentUrl;
};

let unsubscribeNavigate: (() => void) | null = null;

onMounted(() => {
    unsubscribeNavigate = router.on('navigate', navigateHandler);
});

onUnmounted(() => {
    unsubscribeNavigate?.();
    // Ensure the body scroll lock is removed even if the component is
    // unmounted while the drawer is still open (e.g. Inertia navigation
    // re-mounts the layout before the watch can fire).
    if (typeof document !== 'undefined') {
        document.body.classList.remove('overflow-hidden');
    }
});

// Lock body scroll while the drawer is open.
watch(mobileNavOpen, (open) => {
    if (typeof document === 'undefined') {
        return;
    }
    document.body.classList.toggle('overflow-hidden', open);
});

function onBackdropKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeMobileNav();
    }
}
</script>

<template>
    <Head :title="title" />

    <div
        :class="[
            'flex flex-col bg-surface-bg font-sans antialiased md:flex-row',
            props.fullBleed ? 'h-dvh overflow-hidden' : 'min-h-screen',
        ]"
    >
        <!-- Desktop Sidebar -->
        <aside
            class="sticky top-0 z-20 hidden h-screen w-64 flex-col border-r border-outline-glass bg-surface-container text-left md:flex"
        >
            <AppSidebar />
        </aside>

        <!-- Mobile Top Nav -->
        <header
            class="glass-panel sticky top-0 z-30 flex h-16 w-full items-center justify-between gap-2 border-b border-outline-glass px-4 shadow-sm md:hidden"
        >
            <Brand :href="route('dashboard')" />
            <button
                type="button"
                @click="mobileNavOpen = true"
                class="rounded-lg p-2 text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                :title="t('nav.menu')"
                :aria-label="t('nav.menu')"
                :aria-expanded="mobileNavOpen"
                aria-controls="mobile-nav-drawer"
            >
                <Menu :size="20" />
            </button>
        </header>

        <!-- Mobile Nav Drawer (full screen) -->
        <Transition name="nav-drawer">
            <div
                v-if="mobileNavOpen"
                id="mobile-nav-drawer"
                class="fixed inset-0 z-50 flex flex-col bg-surface-container md:hidden"
                role="dialog"
                aria-modal="true"
                @keydown="onBackdropKeydown"
            >
                <div
                    class="flex h-16 shrink-0 items-center justify-between border-b border-outline-glass px-4"
                >
                    <Brand :href="route('dashboard')" />
                    <button
                        type="button"
                        @click="closeMobileNav"
                        class="rounded-lg p-2 text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                        :title="t('nav.close')"
                        :aria-label="t('nav.close')"
                    >
                        <X :size="20" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <AppSidebar :show-brand="false" />
                </div>
            </div>
        </Transition>

        <main
            :class="[
                'flex min-w-0 flex-1 flex-col',
                props.fullBleed && 'min-h-0 overflow-hidden',
            ]"
        >
            <div
                :class="[
                    'flex flex-1 flex-col',
                    props.fullBleed ? 'min-h-0' : 'p-4 md:p-8',
                ]"
            >
                <div
                    :class="[
                        'z-10 flex flex-1 flex-col',
                        props.fullBleed
                            ? 'min-h-0 w-full'
                            : 'mx-auto w-full max-w-7xl',
                    ]"
                >
                    <FlashToasts mobile-header-offset />

                    <div :class="['flex-1', props.fullBleed && 'flex min-h-0']">
                        <slot />
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.nav-drawer-enter-active,
.nav-drawer-leave-active {
    transition:
        transform 0.2s ease,
        opacity 0.2s ease;
}
.nav-drawer-enter-from,
.nav-drawer-leave-to {
    transform: translateX(100%);
    opacity: 0;
}
</style>
