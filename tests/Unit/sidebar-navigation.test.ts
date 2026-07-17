import { describe, expect, test } from 'vitest';
import { canViewShiftCalendar } from '@/lib/sidebar-navigation';

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
});
