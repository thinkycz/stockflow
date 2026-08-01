<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Modal from '@/components/ui/Modal.vue';
import {
    activeDialog,
    finishDialog,
    type DialogVariant,
} from '@/composables/useDialog';

const { t } = useI18n();
const value = ref('');

watch(activeDialog, (dialog) => {
    value.value = dialog?.kind === 'prompt' ? (dialog.defaultValue ?? '') : '';
});

const variant = computed<DialogVariant>(
    () => activeDialog.value?.variant ?? 'default',
);
const buttonVariant = computed(() =>
    variant.value === 'danger'
        ? ('danger' as const)
        : variant.value === 'warning'
          ? ('warning' as const)
          : ('primary' as const),
);
const isValid = computed(() => {
    const dialog = activeDialog.value;
    if (dialog === null) return false;
    if (dialog.kind === 'confirm') {
        return (
            dialog.verification === undefined ||
            value.value === dialog.verification.expected
        );
    }
    return !dialog.required || value.value.trim() !== '';
});

function cancel(): void {
    finishDialog(null);
}

function submit(): void {
    const dialog = activeDialog.value;
    if (dialog === null || !isValid.value) return;
    finishDialog(dialog.kind === 'confirm' ? true : value.value);
}
</script>

<template>
    <Modal
        :open="activeDialog !== null"
        :title="activeDialog?.title"
        layer="alert"
        :close-on-backdrop="false"
        @close="cancel"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <p class="text-sm leading-6 text-on-surface-variant">
                {{ activeDialog?.message }}
            </p>

            <div v-if="activeDialog?.kind === 'prompt'" class="space-y-2">
                <Label
                    for="global-dialog-input"
                    :required="activeDialog.required"
                >
                    {{ activeDialog.label }}
                </Label>
                <Input
                    id="global-dialog-input"
                    v-model="value"
                    autofocus
                    :maxlength="activeDialog.maxLength"
                    :required="activeDialog.required"
                />
            </div>

            <div
                v-else-if="
                    activeDialog?.kind === 'confirm' &&
                    activeDialog.verification
                "
                class="space-y-2"
            >
                <Label for="global-dialog-verification">
                    {{ activeDialog.verification.label }}
                </Label>
                <Input
                    id="global-dialog-verification"
                    v-model="value"
                    autofocus
                    autocomplete="off"
                />
            </div>

            <div
                class="flex flex-col-reverse gap-2 border-t border-outline-glass pt-4 sm:flex-row sm:justify-end"
            >
                <Button type="button" variant="secondary" @click="cancel">
                    {{ t('common.cancel') }}
                </Button>
                <Button
                    type="submit"
                    :variant="buttonVariant"
                    :disabled="!isValid"
                >
                    {{ activeDialog?.confirmLabel ?? t('common.confirm') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>
