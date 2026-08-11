import { describe, expect, test } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { sortShiftsByTime } from '@/lib/shift-calendar';

describe('shift calendar ordering', () => {
    test('places earlier shifts above later shifts regardless of insertion order', () => {
        const shifts = [
            { id: 3, start_time: '18:00', end_time: '22:00' },
            { id: 1, start_time: '06:30', end_time: '12:00' },
            { id: 2, start_time: '12:00', end_time: '18:00' },
        ];

        expect(sortShiftsByTime(shifts).map((shift) => shift.id)).toEqual([
            1, 2, 3,
        ]);
        expect(shifts.map((shift) => shift.id)).toEqual([3, 1, 2]);
    });
});

describe('shift calendar mobile view contract', () => {
    test('all calendar pages use shared compact and full mobile views', () => {
        const component = readFileSync(
            resolve(
                process.cwd(),
                'resources/js/components/ShiftMonthCalendar.vue',
            ),
            'utf8',
        );

        expect(component).toContain('mobile-compact-view');
        expect(component).toContain('mobile-full-view');
        expect(component).toContain('mobileViewsByPage');
        expect(component).not.toContain('mobile-day-view');
        expect(component).not.toContain('mobileMonthOnly');

        for (const page of [
            'shifts/Index.vue',
            'public-shifts/Index.vue',
            'public-shift-requests/Index.vue',
        ]) {
            const source = readFileSync(
                resolve(process.cwd(), 'resources/js/pages', page),
                'utf8',
            );
            expect(source, page).toContain('<ShiftMonthCalendar');
            expect(source, page).not.toContain('mobile-month-only');
        }
    });
});
