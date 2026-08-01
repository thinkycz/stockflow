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
    | 'payroll'
    | 'income_expenses';

export function storeSectionNavigationKeys(
    isAdmin: boolean,
    canViewOperations: boolean,
): StoreSectionNavigationKey[] {
    if (isAdmin) {
        return [
            'dashboard',
            'statements',
            'inventory_counts',
            'reports',
            'shifts',
            'attendance',
            'payroll',
            'income_expenses',
        ];
    }

    return [
        'dashboard',
        'incoming',
        'consumption',
        'statements',
        'inventory_counts',
        ...(canViewOperations ? (['shifts', 'attendance'] as const) : []),
    ];
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
        isRouteOrChild('/payroll') ||
        isRouteOrChild('/income-expenses')
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
