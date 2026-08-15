<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BarChart3,
    BookOpen,
    Boxes,
    CalendarDays,
    ChevronsUpDown,
    ClipboardCheck,
    ClipboardList,
    Gift,
    HardHat,
    HandCoins,
    LayoutDashboard,
    ListChecks,
    LogOut,
    PackageMinus,
    PackagePlus,
    Receipt,
    Settings as SettingsIcon,
    Store as StoreIcon,
    Users,
    WalletCards,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import DropdownMenu from '@/components/ui/DropdownMenu.vue';
import DropdownMenuItem from '@/components/ui/DropdownMenuItem.vue';
import DropdownMenuSeparator from '@/components/ui/DropdownMenuSeparator.vue';
import StoreSwitcher from '@/components/ui/StoreSwitcher.vue';
import { useRoute } from '@/composables/useRoute';
import { useSharedProps } from '@/composables/useSharedProps';
import {
    canViewShiftCalendar,
    limitedUserSectionKeys,
    storeSectionNavigationKeys,
    type StoreSectionNavigationKey,
} from '@/lib/sidebar-navigation';

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

const shiftNavItem = computed<NavItem>(() => ({
    key: 'shifts',
    href: route('shifts.index'),
    label: t('nav.shifts'),
    icon: CalendarDays,
    active: activeUrl.value.startsWith('/shifts'),
}));

const attendanceNavItem = computed<NavItem>(() => ({
    key: 'attendance',
    href: route('attendance.index'),
    label: t('nav.attendance'),
    icon: ClipboardCheck,
    active: activeUrl.value.startsWith('/attendance'),
}));

const dashboardNavItem = computed<NavItem>(() => ({
    key: 'dashboard',
    href: route('dashboard'),
    label: t('nav.dashboard'),
    icon: LayoutDashboard,
    active:
        activeUrl.value === '/dashboard' ||
        activeUrl.value.startsWith('/dashboard?'),
}));

const storeNavItemsByKey = computed<Record<StoreSectionNavigationKey, NavItem>>(
    () => ({
        dashboard: dashboardNavItem.value,
        incoming: {
            key: 'incoming',
            href: route('stock-movements.create', { mode: 'incoming' }),
            label: t('nav.incoming'),
            icon: PackagePlus,
            active: activeUrl.value.startsWith(
                '/stock-movements/create?mode=incoming',
            ),
        },
        consumption: {
            key: 'consumption',
            href: route('stock-movements.create', { mode: 'consumption' }),
            label: t('nav.consumption'),
            icon: PackageMinus,
            active:
                activeUrl.value.startsWith('/stock-movements/create') &&
                !activeUrl.value.includes('mode=incoming'),
        },
        statements: {
            key: 'statements',
            href: route('statements.index'),
            label: t('nav.statements'),
            icon: Receipt,
            active: activeUrl.value.startsWith('/statements'),
        },
        inventory_counts: {
            key: 'inventory_counts',
            href: route('inventory-counts.index'),
            label: t('nav.inventory_counts'),
            icon: ClipboardList,
            active: activeUrl.value.startsWith('/inventory-counts'),
        },
        reports: {
            key: 'reports',
            href: route('reports.index'),
            label: t('nav.reports'),
            icon: BarChart3,
            active:
                activeUrl.value === '/reports' ||
                activeUrl.value.startsWith('/reports?'),
        },
        shifts: shiftNavItem.value,
        attendance: attendanceNavItem.value,
        checklists: {
            key: 'checklists',
            href: route('checklists.index'),
            label: t('nav.checklists'),
            icon: ListChecks,
            active: activeUrl.value.startsWith('/checklists'),
        },
        recipes: {
            key: 'recipes',
            href: route('recipes.index'),
            label: t('nav.recipes'),
            icon: BookOpen,
            active:
                activeUrl.value.startsWith('/recipes') ||
                activeUrl.value.startsWith('/recipe-test'),
        },
        payroll: {
            key: 'payroll',
            href: route('payroll.index'),
            label: t('nav.payroll'),
            icon: HandCoins,
            active: activeUrl.value.startsWith('/payroll'),
        },
        income_expenses: {
            key: 'income_expenses',
            href: route('income-expenses.index'),
            label: t('nav.income_expenses'),
            icon: WalletCards,
            active: activeUrl.value.startsWith('/income-expenses'),
        },
        gift_vouchers: {
            key: 'gift_vouchers',
            href: route('gift-vouchers.index'),
            label: t('nav.gift_vouchers'),
            icon: Gift,
            active: activeUrl.value.startsWith('/gift-voucher'),
        },
    }),
);

const storeNavItems = computed<NavItem[]>(() =>
    storeSectionNavigationKeys(
        isAdmin.value,
        canViewShiftCalendar(
            isAdmin.value,
            auth.value.user?.assigned_store_id ?? null,
        ),
        auth.value.user?.enabled_sections ?? limitedUserSectionKeys,
    ).map((key) => storeNavItemsByKey.value[key]),
);

const adminManagementNavItems = computed<NavItem[]>(() => [
    {
        key: 'items',
        href: route('items.index'),
        label: t('nav.inventory'),
        icon: Boxes,
        active: activeUrl.value.startsWith('/items'),
    },
    {
        key: 'stock_movements',
        href: route('stock-movements.index'),
        label: t('nav.stock_movements'),
        icon: ArrowLeftRight,
        active: activeUrl.value.startsWith('/stock-movements'),
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
    {
        key: 'workers',
        href: route('workers.index'),
        label: t('nav.workers'),
        icon: HardHat,
        active: activeUrl.value.startsWith('/workers'),
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
                key: 'store',
                label: t('nav.section.store'),
                items: storeNavItems.value,
                showStoreSwitcher: true,
            },
            {
                key: 'management',
                label: t('nav.section.management'),
                items: adminManagementNavItems.value,
            },
        ];
    }

    return [
        {
            key: 'store',
            label: t('nav.section.store'),
            items: storeNavItems.value,
            showStoreSwitcher: true,
        },
    ];
});

const userInitials = computed(() => {
    const email = auth.value.user?.email ?? '';
    if (!email) return 'U';
    const parts = email
        .split('@')[0]
        ?.split(/[^a-zA-Z0-9]+/)
        .filter(Boolean);
    if (!parts || parts.length === 0) return 'U';
    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
});

function logout(): void {
    router.post(route('logout'));
}
</script>

<template>
    <div class="flex h-full flex-col px-4 py-6 text-left">
        <div v-if="showBrand" class="mb-8 flex items-center gap-3 px-2">
            <Brand :href="route('dashboard')" />
        </div>

        <nav
            class="flex-1 space-y-4 overflow-y-auto"
            :aria-label="t('nav.main')"
        >
            <div
                v-for="section in navSections"
                :key="section.key"
                :data-testid="`nav-section-${section.key}`"
                class="space-y-1"
            >
                <p
                    class="px-3 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
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
                    :data-testid="`nav-item-${item.key}`"
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

        <DropdownMenu
            :label="t('nav.user_menu')"
            placement="right-end"
            trigger-class="flex w-full items-center justify-between gap-2 border-t border-outline-glass pt-4 px-2"
        >
            <template #trigger>
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
                            {{ auth.user?.email ?? '' }}
                        </p>
                    </div>
                </div>
                <ChevronsUpDown
                    :size="14"
                    class="shrink-0 text-on-surface-variant"
                />
            </template>
            <div class="border-b border-outline-glass px-3 py-2.5">
                <p
                    class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant"
                >
                    {{ t('nav.signed_in_as') }}
                </p>
                <p
                    class="mt-0.5 truncate text-sm font-semibold text-on-surface"
                >
                    {{ auth.user?.email ?? '' }}
                </p>
            </div>
            <DropdownMenuItem v-if="isAdmin" :href="route('settings.show')">
                <SettingsIcon :size="16" />
                {{ t('nav.settings') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator v-if="isAdmin" />
            <DropdownMenuItem tone="danger" @click="logout">
                <LogOut :size="16" />
                {{ t('nav.logout') }}
            </DropdownMenuItem>
        </DropdownMenu>
    </div>
</template>
