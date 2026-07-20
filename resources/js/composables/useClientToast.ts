import { readonly, ref } from 'vue';

type ClientToast = {
    id: number;
    type: 'success' | 'error';
    message: string;
};

const clientToast = ref<ClientToast | null>(null);
let nextToastId = 0;

function showClientToast(type: ClientToast['type'], message: string): void {
    clientToast.value = {
        id: ++nextToastId,
        type,
        message,
    };
}

export function showSuccessToast(message: string): void {
    showClientToast('success', message);
}

export function showErrorToast(message: string): void {
    showClientToast('error', message);
}

export function useClientToast() {
    return {
        clientToast: readonly(clientToast),
    };
}
