import { describe, expect, test } from 'vitest';
import {
    showErrorToast,
    showSuccessToast,
    useClientToast,
} from '@/composables/useClientToast';

describe('client toasts', () => {
    test('publishes repeatable quick-action feedback to the global toast host', () => {
        const { clientToast } = useClientToast();

        showSuccessToast('Shift added.');
        const firstId = clientToast.value?.id;
        expect(clientToast.value).toMatchObject({
            type: 'success',
            message: 'Shift added.',
        });

        showSuccessToast('Shift added.');
        expect(clientToast.value?.id).toBeGreaterThan(firstId ?? 0);

        showErrorToast('Shift could not be added.');
        expect(clientToast.value).toMatchObject({
            type: 'error',
            message: 'Shift could not be added.',
        });
    });
});
