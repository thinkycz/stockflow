<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BarChart3,
    Boxes,
    LayoutDashboard,
    LogOut,
    Settings as SettingsIcon,
    Store as StoreIcon,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';

defineProps<{
    title: string;
}>();

const { auth } = useSharedProps();
const { t } = useI18n();

useBoundLocale();

const activeUrl = computed(() => usePage().url);

const navItems = computed(() => [
    {
        key: 'dashboard',
        href: '/dashboard',
        label: t('nav.dashboard'),
        icon: LayoutDashboard,
        active:
            activeUrl.value === '/dashboard' ||
            activeUrl.value.startsWith('/dashboard?'),
    },
    {
        key: 'items',
        href: '/items',
        label: t('nav.inventory'),
        icon: Boxes,
        active: activeUrl.value.startsWith('/items'),
    },
    {
        key: 'stock_movements',
        href: '/stock-movements',
        label: t('nav.stock_movements'),
        icon: ArrowLeftRight,
        active: activeUrl.value.startsWith('/stock-movements'),
    },
    {
        key: 'stores',
        href: '/stores',
        label: t('nav.stores'),
        icon: StoreIcon,
        active: activeUrl.value.startsWith('/stores'),
    },
    {
        key: 'reports',
        href: '/reports',
        label: t('nav.reports'),
        icon: BarChart3,
        active: activeUrl.value.startsWith('/reports'),
    },
]);

const settingsActive = computed(() => activeUrl.value.startsWith('/settings'));

const userInitials = computed(() => {
    const email = auth.value.user?.email ?? '';
    if (!email) return 'DU';
    return email.substring(0, 2).toUpperCase();
});

function logout(): void {
    router.post('/logout');
}
import { useRoute } from '@/composables/useRoute';

const route = useRoute();
void route; // referenced from the <template>
</script>

<template>
    <Head :title="title" />

    <div
        class="flex min-h-screen flex-col bg-surface-bg font-sans antialiased md:flex-row"
    >
        <!-- Desktop Sidebar -->
        <aside
            class="sticky top-0 z-20 hidden h-screen w-64 flex-col border-r border-outline-glass bg-surface-container px-4 py-6 text-left md:flex"
        >
            <div class="mb-8 flex cursor-default items-center gap-3 px-2">
                <Brand href="route('dashboard')" />
            </div>

            <nav class="flex-1 space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    :class="[
                        'flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold transition',
                        item.active
                            ? 'bg-surface-container-lowest text-primary shadow-[inset_0_0_0_1px_rgba(15,23,42,0.06)]'
                            : 'text-on-surface-variant hover:bg-surface-container-low',
                    ]"
                >
                    <component :is="item.icon" :size="16" />
                    {{ item.label }}
                </Link>
            </nav>

            <div
                class="flex items-center justify-between gap-2 border-t border-outline-glass pt-4 px-2"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-outline-glass bg-surface-container-lowest font-heading text-xs font-bold text-primary"
                    >
                        {{ userInitials }}
                    </div>
                    <div class="min-w-0 overflow-hidden">
                        <p
                            class="truncate text-xs font-semibold text-on-surface"
                        >
                            {{
                                auth.user
                                    ? auth.user.email.split('@')[0]
                                    : 'User'
                            }}
                        </p>
                        <p
                            class="truncate text-[9px] font-medium text-on-surface-variant opacity-85"
                        >
                            {{ auth.user ? auth.user.email : '' }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Link
                        v-if="auth.user"
                        href="route('settings.edit')"
                        :class="[
                            'rounded-lg p-1.5 transition-colors',
                            settingsActive
                                ? 'bg-surface-container-lowest text-primary shadow-[inset_0_0_0_1px_rgba(15,23,42,0.06)]'
                                : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary',
                        ]"
                        :title="t('nav.settings')"
                        :aria-label="t('nav.settings')"
                    >
                        <SettingsIcon :size="14" />
                    </Link>
                    <button
                        @click="logout"
                        class="cursor-pointer rounded-lg p-1.5 text-on-surface-variant transition-all hover:bg-rose-50/50 hover:text-error-red"
                        :title="t('nav.logout')"
                        :aria-label="t('nav.logout')"
                    >
                        <LogOut :size="14" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Mobile Top Nav -->
        <header
            class="glass-panel sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-outline-glass px-4 shadow-sm md:hidden"
        >
            <div class="flex items-center gap-2">
                <Brand href="route('dashboard')" />
            </div>
            <div class="flex items-center gap-1">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    :class="[
                        'rounded-lg p-2 transition',
                        item.active
                            ? 'bg-surface-container-lowest text-primary'
                            : 'text-on-surface-variant',
                    ]"
                >
                    <component :is="item.icon" :size="16" />
                </Link>
                <Link
                    v-if="auth.user"
                    href="route('settings.edit')"
                    :class="[
                        'rounded-lg p-2 transition',
                        settingsActive
                            ? 'bg-surface-container-lowest text-primary'
                            : 'text-on-surface-variant',
                    ]"
                    :title="t('nav.settings')"
                    :aria-label="t('nav.settings')"
                >
                    <SettingsIcon :size="16" />
                </Link>
                <button
                    @click="logout"
                    class="rounded-lg p-2 text-on-surface-variant transition hover:text-error-red"
                >
                    <LogOut :size="16" />
                </button>
            </div>
        </header>

        <main class="flex min-h-screen flex-1 flex-col overflow-x-hidden">
            <div class="relative flex flex-1 flex-col p-4 md:p-8">
                <div
                    class="pointer-events-none absolute top-1/2 left-1/2 h-[70vw] w-[70vw] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/5 blur-[100px]"
                ></div>

                <div class="z-10 flex flex-1 flex-col max-w-7xl w-full mx-auto">
                    <FlashAlerts />

                    <div class="flex-1">
                        <slot />
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
