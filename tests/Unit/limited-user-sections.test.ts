import { describe, expect, test } from 'vitest';
import { canAccessLimitedSection } from '@/lib/limited-user-sections';

describe('limited user dashboard actions', () => {
    test('filters quick actions by their section independently', () => {
        const enabledSections = ['incoming', 'statements'] as const;

        expect(canAccessLimitedSection([...enabledSections], 'incoming')).toBe(
            true,
        );
        expect(
            canAccessLimitedSection([...enabledSections], 'consumption'),
        ).toBe(false);
    });
});
