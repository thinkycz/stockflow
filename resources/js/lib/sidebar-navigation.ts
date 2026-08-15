export function canViewShiftCalendar(
    isAdmin: boolean,
    assignedStoreId: number | null,
): boolean {
    return isAdmin || assignedStoreId !== null;
}

export type StoreSectionNavigationKey =
    | 'dashboard'
    | 'incoming'
    | 'consumption'
    | 'statements'
    | 'inventory_counts'
    | 'reports'
    | 'shifts'
    | 'attendance'
    | 'checklists'
    | 'recipes'
    | 'payroll'
    | 'income_expenses'
    | 'gift_vouchers';

export function storeSectionNavigationKeys(
    isAdmin: boolean,
    canViewOperations: boolean,
    enabledSections: LimitedUserSection[] = limitedUserSectionKeys,
): StoreSectionNavigationKey[] {
    if (isAdmin) {
        return [
            'dashboard',
            'statements',
            'inventory_counts',
            'reports',
            'shifts',
            'attendance',
            'checklists',
            'recipes',
            'payroll',
            'income_expenses',
            'gift_vouchers',
        ];
    }

    return [
        'dashboard',
        'incoming',
        'consumption',
        'statements',
        'inventory_counts',
        ...(canViewOperations ? (['shifts', 'attendance'] as const) : []),
        'recipes',
        'gift_vouchers',
    ].filter(
        (key): key is StoreSectionNavigationKey =>
            key === 'dashboard' ||
            enabledSections.includes(key as LimitedUserSection),
    );
}

export function isStoreSectionUrl(url: string, isAdmin: boolean): boolean {
    const parsed = new URL(url, 'https://stockflow.local');
    const path = parsed.pathname;
    const isRouteOrChild = (routePath: string): boolean =>
        path === routePath || path.startsWith(`${routePath}/`);

    if (
        path === '/dashboard' ||
        isRouteOrChild('/statements') ||
        isRouteOrChild('/inventory-counts') ||
        isRouteOrChild('/reports') ||
        isRouteOrChild('/shifts') ||
        isRouteOrChild('/attendance') ||
        isRouteOrChild('/checklists') ||
        isRouteOrChild('/recipes') ||
        isRouteOrChild('/recipe-tests') ||
        isRouteOrChild('/recipe-test-results') ||
        isRouteOrChild('/payroll') ||
        isRouteOrChild('/income-expenses') ||
        isRouteOrChild('/gift-vouchers')
    ) {
        return true;
    }

    if (isAdmin || path !== '/stock-movements/create') {
        return false;
    }

    return ['incoming', 'consumption'].includes(
        parsed.searchParams.get('mode') ?? '',
    );
}
import { limitedUserSectionKeys } from '@/lib/limited-user-sections';
import type { LimitedUserSection } from '@/types';

export { limitedUserSectionKeys } from '@/lib/limited-user-sections';
