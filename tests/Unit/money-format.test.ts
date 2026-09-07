import { expect, test } from 'vitest';
import { setActiveLocale } from '@/i18n';
import { formatMoney, formatSignedMoney } from '@/lib/format';

test.each(['cs', 'en', 'sk'] as const)(
    'money remains Czech in %s',
    (locale) => {
        setActiveLocale(locale);
        expect(formatMoney('6840.57')).toBe('6\u00a0840,57\u00a0Kč');
        expect(formatMoney(6875.55)).toBe('6\u00a0875,55\u00a0Kč');
        expect(formatMoney(0)).toBe('0,00\u00a0Kč');
        expect(formatMoney('-1234.5')).toBe('-1\u00a0234,50\u00a0Kč');
        expect(formatSignedMoney(5)).toBe('+5,00\u00a0Kč');
        for (const missing of [null, undefined, '', 'invalid', Number.NaN])
            expect(formatMoney(missing)).toBe('—');
    },
);
