import { describe, expect, test } from 'vitest';
import { storeSwitchRefreshUrl } from '@/lib/store-switch-navigation';

describe('store switch navigation', () => {
    test('removes a stale store override while retaining unrelated filters', () => {
        expect(
            storeSwitchRefreshUrl(
                'https://stockflow.test/statements?store_id=12&year=2026&month=7#daily',
            ),
        ).toBe('/statements?year=2026&month=7#daily');
    });

    test('keeps the current URL when it has no store override', () => {
        expect(
            storeSwitchRefreshUrl(
                'https://stockflow.test/dashboard?period_days=30',
            ),
        ).toBe('/dashboard?period_days=30');
    });
});
