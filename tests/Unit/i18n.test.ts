import { describe, expect, test } from 'vitest';
import { isSupportedLocale, createAppI18n, SUPPORTED_LOCALES } from '@/i18n';

describe('i18n configurations', () => {
    test('supported locales are configured correctly', () => {
        expect(SUPPORTED_LOCALES).toContain('en');
        expect(SUPPORTED_LOCALES).toContain('cs');
        expect(SUPPORTED_LOCALES).toContain('sk');
    });

    test('isSupportedLocale checks values correctly', () => {
        expect(isSupportedLocale('en')).toBe(true);
        expect(isSupportedLocale('cs')).toBe(true);
        expect(isSupportedLocale('sk')).toBe(true);
        expect(isSupportedLocale('fr')).toBe(false);
        expect(isSupportedLocale('')).toBe(false);
    });

    test('createAppI18n falls back to en for unsupported locales', () => {
        const i18n = createAppI18n('fr');
        expect(i18n.global.locale.value).toBe('en');
    });

    test('createAppI18n resolves correct locale when supported', () => {
        const i18n = createAppI18n('cs');
        expect(i18n.global.locale.value).toBe('cs');

        const i18nSk = createAppI18n('sk');
        expect(i18nSk.global.locale.value).toBe('sk');
    });
});
