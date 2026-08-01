import { shallowRef } from 'vue';

export type DialogVariant = 'default' | 'warning' | 'danger';

export type ConfirmDialogOptions = {
    title: string;
    message: string;
    confirmLabel?: string;
    variant?: DialogVariant;
    verification?: { label: string; expected: string };
};

export type PromptDialogOptions = {
    title: string;
    message: string;
    label: string;
    defaultValue?: string;
    confirmLabel?: string;
    required?: boolean;
    maxLength?: number;
    variant?: DialogVariant;
};

export type DialogRequest =
    | ({ kind: 'confirm' } & ConfirmDialogOptions & {
              resolve: (value: boolean) => void;
          })
    | ({ kind: 'prompt' } & PromptDialogOptions & {
              resolve: (value: string | null) => void;
          });

export const activeDialog = shallowRef<DialogRequest | null>(null);
const queue: DialogRequest[] = [];

function enqueue(request: DialogRequest): void {
    if (activeDialog.value === null) activeDialog.value = request;
    else queue.push(request);
}

export function finishDialog(value: boolean | string | null): void {
    const request = activeDialog.value;
    if (request === null) return;

    if (request.kind === 'confirm') request.resolve(value === true);
    else request.resolve(typeof value === 'string' ? value : null);

    activeDialog.value = queue.shift() ?? null;
}

export function useDialog(): {
    confirm: (options: ConfirmDialogOptions) => Promise<boolean>;
    prompt: (options: PromptDialogOptions) => Promise<string | null>;
} {
    return {
        confirm: (options) =>
            new Promise<boolean>((resolve) =>
                enqueue({
                    kind: 'confirm',
                    variant: 'default',
                    ...options,
                    resolve,
                }),
            ),
        prompt: (options) =>
            new Promise<string | null>((resolve) =>
                enqueue({
                    kind: 'prompt',
                    variant: 'default',
                    required: false,
                    defaultValue: '',
                    ...options,
                    resolve,
                }),
            ),
    };
}
