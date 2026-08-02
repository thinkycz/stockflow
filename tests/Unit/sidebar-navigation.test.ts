import { describe, expect, test } from 'vitest';
import {
    canViewShiftCalendar,
    isStoreSectionUrl,
    storeSectionNavigationKeys,
} from '@/lib/sidebar-navigation';

describe('sidebar navigation', () => {
    test('shows the shift calendar to a limited user with an assigned store', () => {
        expect(canViewShiftCalendar(false, 42)).toBe(true);
    });

    test('hides the shift calendar from a limited user without an assigned store', () => {
        expect(canViewShiftCalendar(false, null)).toBe(false);
    });

    test('shows the shift calendar to an admin without an assigned store', () => {
        expect(canViewShiftCalendar(true, null)).toBe(true);
    });

    test('places the noticeboard first in both store section variants', () => {
        expect(storeSectionNavigationKeys(true, true)[0]).toBe('dashboard');
        expect(storeSectionNavigationKeys(false, true)[0]).toBe('dashboard');
    });

    test('places payroll before income and expenses for admins only', () => {
        const admin = storeSectionNavigationKeys(true, true);
        const limited = storeSectionNavigationKeys(false, true);

        expect(admin.indexOf('checklists')).toBe(
            admin.indexOf('attendance') + 1,
        );
        expect(admin.indexOf('recipes')).toBe(admin.indexOf('checklists') + 1);
        expect(admin.indexOf('payroll')).toBe(admin.indexOf('recipes') + 1);
        expect(admin.indexOf('income_expenses')).toBe(
            admin.indexOf('payroll') + 1,
        );
        expect(limited).not.toContain('payroll');
        expect(limited).not.toContain('income_expenses');
        expect(limited.indexOf('recipes')).toBe(
            limited.indexOf('attendance') + 1,
        );
        expect(admin).not.toContain('statistics');
    });

    test('classifies admin store section pages including child routes', () => {
        expect(isStoreSectionUrl('/dashboard?status=active', true)).toBe(true);
        expect(isStoreSectionUrl('/statements/history', true)).toBe(true);
        expect(isStoreSectionUrl('/inventory-counts/42', true)).toBe(true);
        expect(isStoreSectionUrl('/reports/statistics', true)).toBe(true);
        expect(isStoreSectionUrl('/shifts?month=7', true)).toBe(true);
        expect(isStoreSectionUrl('/attendance/report', true)).toBe(true);
        expect(isStoreSectionUrl('/checklists?tab=history', true)).toBe(true);
        expect(isStoreSectionUrl('/recipes/42', true)).toBe(true);
        expect(isStoreSectionUrl('/payroll?year=2026', true)).toBe(true);
        expect(isStoreSectionUrl('/income-expenses?year=2026', true)).toBe(
            true,
        );
        expect(isStoreSectionUrl('/reports-archive', true)).toBe(false);
    });

    test('keeps management pages outside the admin store section', () => {
        expect(isStoreSectionUrl('/items', true)).toBe(false);
        expect(isStoreSectionUrl('/stock-movements', true)).toBe(false);
        expect(
            isStoreSectionUrl('/stock-movements/create?mode=incoming', true),
        ).toBe(false);
        expect(isStoreSectionUrl('/stores/2', true)).toBe(false);
        expect(isStoreSectionUrl('/users', true)).toBe(false);
        expect(isStoreSectionUrl('/workers', true)).toBe(false);
        expect(isStoreSectionUrl('/settings', true)).toBe(false);
    });

    test('includes limited receipt and consumption forms in the store section', () => {
        expect(
            isStoreSectionUrl('/stock-movements/create?mode=incoming', false),
        ).toBe(true);
        expect(
            isStoreSectionUrl(
                '/stock-movements/create?mode=consumption',
                false,
            ),
        ).toBe(true);
        expect(isStoreSectionUrl('/stock-movements', false)).toBe(false);
    });
});
