<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BarChart3,
    Boxes,
    ClipboardList,
    LayoutDashboard,
    LogOut,
    Receipt,
    Settings as SettingsIcon,
    Store as StoreIcon,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import StoreSwitcher from '@/components/ui/StoreSwitcher.vue';
import { useRoute } from '@/composables/useRoute';
import { useSharedProps } from '@/composables/useSharedProps';

withDefaults(
    defineProps<{
        showBrand?: boolean;
    }>(),
    {
        showBrand: true,
    },
);

const { auth } = useSharedProps();
const { t } = useI18n();

const route = useRoute();

const activeUrl = computed(() => usePage().url);

const isAdmin = computed(() => auth.value.user?.is_admin === true);

type NavItem = {
    key: string;
    href: string;
    label: string;
    icon: typeof LayoutDashboard;
    active: boolean;
};

const adminStoreNavItems = computed<NavItem[]>(() => [
    {
        key: 'stock_movements',
        href: route('stock-movements.index'),
        label: t('nav.stock_movements'),
        icon: ArrowLeftRight,
        active: activeUrl.value.startsWith('/stock-movements'),
    },
    {
        key: 'statements',
        href: route('statements.index'),
        label: t('nav.statements'),
        icon: Receipt,
        active: activeUrl.value.startsWith('/statements'),
    },
    {
        key: 'inventory_counts',
        href: route('inventory-counts.index'),
        label: t('nav.inventory_counts'),
        icon: ClipboardList,
        active: activeUrl.value.startsWith('/inventory-counts'),
    },
    {
        key: 'reports',
        href: route('reports.index'),
        label: t('nav.reports'),
        icon: BarChart3,
        active:
            activeUrl.value === '/reports' ||
            activeUrl.value.startsWith('/reports?'),
    },
    {
        key: 'statistics',
        href: route('reports.statistics'),
        label: t('nav.statistics'),
        icon: TrendingUp,
        active: activeUrl.value.startsWith('/reports/statistics'),
    },
]);

const adminManagementNavItems = computed<NavItem[]>(() => [
    {
        key: 'items',
        href: route('items.index'),
        label: t('nav.inventory'),
        icon: Boxes,
        active: activeUrl.value.startsWith('/items'),
    },
    {
        key: 'stores',
        href: route('stores.index'),
        label: t('nav.stores'),
        icon: StoreIcon,
        active: activeUrl.value.startsWith('/stores'),
    },
    {
        key: 'users',
        href: route('users.index'),
        label: t('nav.users'),
        icon: Users,
        active: activeUrl.value.startsWith('/users'),
    },
]);

const dashboardNavItem = computed<NavItem>(() => ({
    key: 'dashboard',
    href: route('dashboard'),
    label: t('nav.dashboard'),
    icon: LayoutDashboard,
    active:
        activeUrl.value === '/dashboard' ||
        activeUrl.value.startsWith('/dashboard?'),
}));

const limitedStoreNavItems = computed<NavItem[]>(() => [
    {
        key: 'statements',
        href: route('statements.index'),
        label: t('nav.statements'),
        icon: Receipt,
        active: activeUrl.value.startsWith('/statements'),
    },
    {
        key: 'inventory_counts',
        href: route('inventory-counts.index'),
        label: t('nav.inventory_counts'),
        icon: ClipboardList,
        active: activeUrl.value.startsWith('/inventory-counts'),
    },
]);

type NavSection = {
    key: string;
    label: string | null;
    items: NavItem[];
    showStoreSwitcher?: boolean;
};

const navSections = computed<NavSection[]>(() => {
    if (isAdmin.value) {
        return [
            {
                key: 'management',
                label: t('nav.section.management'),
                items: adminManagementNavItems.value,
            },
            {
                key: 'store',
                label: t('nav.section.store'),
                items: adminStoreNavItems.value,
                showStoreSwitcher: true,
            },
        ];
    }

    return [
        {
            key: 'store',
            label: t('nav.section.store'),
            items: limitedStoreNavItems.value,
            showStoreSwitcher: true,
        },
    ];
});

const settingsActive = computed(() => activeUrl.value.startsWith('/settings'));

const userInitials = computed(() => {
    const email = auth.value.user?.email ?? '';
    if (!email) return 'DU';
    return email.substring(0, 2).toUpperCase();
});

function logout(): void {
    router.post(route('logout'));
}
</script>

<template>
    <div class="flex h-full flex-col px-4 py-6 text-left">
        <div
            v-if="showBrand"
            class="mb-8 flex cursor-default items-center gap-3 px-2"
        >
            <Brand :href="route('dashboard')" />
        </div>

        <nav class="flex-1 space-y-4 overflow-y-auto">
            <Link
                :key="dashboardNavItem.key"
                :href="dashboardNavItem.href"
                :class="[
                    'flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold transition',
                    dashboardNavItem.active
                        ? 'bg-surface-container-lowest text-primary shadow-[inset_0_0_0_1px_rgba(15,23,42,0.06)]'
                        : 'text-on-surface-variant hover:bg-surface-container-low',
                ]"
            >
                <component :is="dashboardNavItem.icon" :size="16" />
                {{ dashboardNavItem.label }}
            </Link>

            <div
                v-for="section in navSections"
                :key="section.key"
                class="space-y-1"
            >
                <p
                    class="px-3 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant/70"
                >
                    {{ section.label }}
                </p>
                <StoreSwitcher
                    v-if="section.showStoreSwitcher === true"
                    integrated
                    class="mb-1"
                />
                <Link
                    v-for="item in section.items"
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
            </div>
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
                    <p class="truncate text-xs font-semibold text-on-surface">
                        {{ auth.user ? auth.user.email.split('@')[0] : 'User' }}
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
                    :href="route('settings.show')"
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
    </div>
</template>
