import { describe, expect, test } from 'vitest';
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
