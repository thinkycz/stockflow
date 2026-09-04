import { describe, expect, test, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import {
    sortShiftsByTime,
    calendarMonthDays,
    buildCalendarDays,
} from '@/features/shifts/shift-calendar';

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
                'resources/js/features/shifts/components/ShiftMonthCalendar.vue',
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

describe('shared calendar projection', () => {
    test('month grids remain consecutive across DST and year boundaries in western time zones', () => {
        vi.stubEnv('TZ', 'America/Los_Angeles');
        try {
            for (const [year, month, currentDays] of [
                [2026, 3, 31],
                [2026, 12, 31],
                [2028, 2, 29],
            ]) {
                const days = calendarMonthDays(year, month);
                expect(days).toHaveLength(42);
                expect(new Set(days.map((day) => day.date)).size).toBe(42);
                expect(days.filter((day) => day.isCurrentMonth)).toHaveLength(
                    currentDays,
                );
                expect(
                    days.every(
                        (day, index) =>
                            index === 0 || day.date > days[index - 1].date,
                    ),
                ).toBe(true);
            }
        } finally {
            vi.unstubAllEnvs();
        }
    });
    test('projection orders events and keeps original rows unchanged', () => {
        const shifts = [
            {
                id: 2,
                worker_id: 9,
                date: '2026-09-04',
                start_time: '13:00',
                end_time: '17:00',
            },
            {
                id: 1,
                worker_id: 9,
                date: '2026-09-04',
                start_time: '08:00',
                end_time: '12:00',
            },
        ];
        const projected = buildCalendarDays(2026, 9, [], shifts, []);
        expect(
            projected
                .find((day) => day.date === '2026-09-04')
                ?.shifts.map((shift) => shift.id),
        ).toEqual([1, 2]);
        expect(shifts.map((shift) => shift.id)).toEqual([2, 1]);
    });
});
