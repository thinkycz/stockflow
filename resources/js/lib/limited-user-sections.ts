import type { LimitedUserSection } from '@/types';

export const limitedUserSectionKeys: LimitedUserSection[] = [
    'incoming',
    'consumption',
    'statements',
    'inventory_counts',
    'shifts',
    'attendance',
    'checklists',
    'recipes',
    'gift_vouchers',
];

export function canAccessLimitedSection(
    enabledSections: LimitedUserSection[],
    section: LimitedUserSection,
): boolean {
    return enabledSections.includes(section);
}
