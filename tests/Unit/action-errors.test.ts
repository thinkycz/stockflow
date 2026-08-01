import { describe, expect, test, vi } from 'vitest';
import { withActionErrorToast } from '@/lib/action-errors';
import { useClientToast } from '@/composables/useClientToast';

describe('action validation errors', () => {
    test('shows the first error in a persistent toast and preserves the callback', () => {
        const original = vi.fn();
        const options = withActionErrorToast({ onError: original });

        const errors = {
            report: ['Close payroll first.'],
            other: ['Later.'],
        };
        options.onError?.(
            errors as unknown as Parameters<
                NonNullable<typeof options.onError>
            >[0],
        );

        expect(useClientToast().clientToast.value).toMatchObject({
            type: 'error',
            message: 'Close payroll first.',
        });
        expect(original).toHaveBeenCalledOnce();
        expect(original).toHaveBeenCalledWith(errors, undefined);
    });
});
