export function canViewShiftCalendar(
    isAdmin: boolean,
    assignedStoreId: number | null,
): boolean {
    return isAdmin || assignedStoreId !== null;
}
