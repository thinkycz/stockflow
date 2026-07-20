import { describe, expect, test } from 'vitest';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import {
    isSupportedLocale,
    createAppI18n,
    messages,
    SUPPORTED_LOCALES,
} from '@/i18n';

function flattenKeys(value: Record<string, unknown>, prefix = ''): Set<string> {
    const keys = new Set<string>();

    for (const [key, child] of Object.entries(value)) {
        const path = prefix === '' ? key : `${prefix}.${key}`;

        if (
            child !== null &&
            typeof child === 'object' &&
            !Array.isArray(child)
        ) {
            for (const nested of flattenKeys(
                child as Record<string, unknown>,
                path,
            )) {
                keys.add(nested);
            }
        } else {
            keys.add(path);
        }
    }

    return keys;
}

function sourceFiles(directory: string): string[] {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            return sourceFiles(path);
        }

        return /\.(js|ts|vue)$/.test(entry.name) ? [path] : [];
    });
}

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

    test('all locales expose the same translation keys', () => {
        const englishKeys = [...flattenKeys(messages.en)].sort();

        expect([...flattenKeys(messages.cs)].sort()).toEqual(englishKeys);
        expect([...flattenKeys(messages.sk)].sort()).toEqual(englishKeys);
    });

    test('all statically referenced translation keys exist', () => {
        const knownKeys = flattenKeys(messages.en);
        const missing = new Set<string>();
        const staticTranslation =
            /(?:\bt|\.t)\(\s*(['"])([^'"]+)\1(?=\s*[,)]\s*)/g;

        for (const file of sourceFiles(join(process.cwd(), 'resources/js'))) {
            for (const match of readFileSync(file, 'utf8').matchAll(
                staticTranslation,
            )) {
                const key = match[2];

                if (key !== undefined && !knownKeys.has(key)) {
                    missing.add(key);
                }
            }
        }

        expect([...missing].sort()).toEqual([]);
    });
});
