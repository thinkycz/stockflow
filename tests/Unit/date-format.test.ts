import { describe, expect, test } from 'vitest';
import { setActiveLocale } from '@/i18n';
import { formatDate, formatDateTime } from '@/lib/format';
import { formatDateInput, parseCzechDateInput } from '@/lib/date-input';
import { formatCzechDateRange } from '@/composables/useCzechDate';

describe('Czech dates across languages', () => {
    test.each(['cs', 'en', 'sk'] as const)(
        '%s uses exact Czech calendar notation',
        (locale) => {
            setActiveLocale(locale);
            expect(formatDate('2026-09-06')).toBe('6.9.2026');
            expect(formatDateTime('2026-09-05T22:05:00Z')).toBe(
                '6.9.2026 00:05',
            );
            expect(formatCzechDateRange('2026-09-06', '2026-09-07')).toBe(
                '6.9.2026 – 7.9.2026',
            );
        },
    );
    test('handles Prague daylight saving transitions', () => {
        expect(formatDateTime('2026-03-29T00:30:00Z')).toBe('29.3.2026 01:30');
        expect(formatDateTime('2026-03-29T01:30:00Z')).toBe('29.3.2026 03:30');
        expect(formatDateTime('2026-10-25T00:30:00Z')).toBe('25.10.2026 02:30');
        expect(formatDateTime('2026-10-25T01:30:00Z')).toBe('25.10.2026 02:30');
    });
    test('handles empty values and rejects impossible calendar dates', () => {
        for (const value of [
            null,
            undefined,
            '',
            'invalid',
            '2026-02-29',
            '2026-04-31',
        ])
            expect(formatDate(value)).toBe('—');
        expect(formatDate('2024-02-29')).toBe('29.2.2024');
    });
});

describe('Czech date input conversion', () => {
    test('round-trips ISO form values without timezone changes', () => {
        expect(formatDateInput('2026-09-06')).toBe('6.9.2026');
        expect(parseCzechDateInput('6.9.2026')).toBe('2026-09-06');
        expect(formatDateInput('2026-09-06T00:05')).toBe('6.9.2026 00:05');
        expect(parseCzechDateInput('6.9.2026 0:05', true)).toBe(
            '2026-09-06T00:05',
        );
        expect(parseCzechDateInput('29.2.2024')).toBe('2024-02-29');
    });
    test.each([
        '',
        '2026-09-06',
        '29.2.2026',
        '31.4.2026',
        '0.1.2026',
        '1.13.2026',
        '1.1.0000',
    ])('rejects invalid date %s', (value) => {
        expect(parseCzechDateInput(value)).toBeNull();
    });
    test.each(['6.9.2026 24:00', '6.9.2026 12:60', '6.9.2026'])(
        'rejects invalid time %s',
        (value) => {
            expect(parseCzechDateInput(value, true)).toBeNull();
        },
    );
});
