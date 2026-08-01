import type { VisitOptions } from '@inertiajs/core';
import { showErrorToast } from '@/composables/useClientToast';

export function firstActionError(value: unknown): string | null {
    if (typeof value === 'string') {
        return value === '' ? null : value;
    }

    if (Array.isArray(value)) {
        for (const item of value) {
            const message = firstActionError(item);

            if (message !== null) {
                return message;
            }
        }

        return null;
    }

    if (typeof value === 'object' && value !== null) {
        return firstActionError(Object.values(value));
    }

    return null;
}

export function withActionErrorToast(options: VisitOptions = {}): VisitOptions {
    const onError = options.onError;

    return {
        ...options,
        headers: {
            ...options.headers,
            'X-StockFlow-Action': 'true',
        },
        onError: (errors, metadata) => {
            const message = firstActionError(errors);

            if (message !== null) {
                showErrorToast(message);
            }

            onError?.(errors, metadata);
        },
    };
}
