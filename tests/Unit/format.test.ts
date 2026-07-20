import { describe, expect, test } from 'vitest';
import { setActiveLocale } from '@/i18n';
import { formatSignedNumber, formatStockQuantity } from '@/lib/format';

describe('signed number formatting', () => {
    test('makes both inventory reconciliation directions explicit', () => {
        setActiveLocale('en');

        expect(formatSignedNumber(2)).toBe('+2');
        expect(formatSignedNumber(-3)).toBe('-3');
        expect(formatSignedNumber(0)).toBe('0');
        expect(formatStockQuantity(1.25)).toBe('1.25');
    });
});
