import { createI18n } from 'vue-i18n';
import en from './en.json';
import cs from './cs.json';
import sk from './sk.json';

export const SUPPORTED_LOCALES = ['en', 'cs', 'sk'] as const;

export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export function isSupportedLocale(value: string): value is SupportedLocale {
    return (SUPPORTED_LOCALES as readonly string[]).includes(value);
}

export const messages = {
    en,
    cs,
    sk,
} as const;

export type MessageSchema = typeof en;

export function createAppI18n(locale: string) {
    return createI18n<[MessageSchema], SupportedLocale>({
        legacy: false,
        locale: isSupportedLocale(locale) ? locale : 'en',
        fallbackLocale: 'en',
        messages: messages as Record<SupportedLocale, MessageSchema>,
    });
}
