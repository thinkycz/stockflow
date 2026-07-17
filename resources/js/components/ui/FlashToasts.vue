<script setup lang="ts">
import { CircleAlert, CircleCheck, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSharedProps } from '@/composables/useSharedProps';

type ToastState = {
    hovered: boolean;
    focused: boolean;
};

const SUCCESS_DURATION = 5000;

const props = withDefaults(
    defineProps<{
        mobileHeaderOffset?: boolean;
    }>(),
    {
        mobileHeaderOffset: false,
    },
);

const { t } = useI18n();
const { flash } = useSharedProps();

const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const successState: ToastState = {
    hovered: false,
    focused: false,
};

let successTimer: ReturnType<typeof setTimeout> | null = null;
let successTimerStartedAt = 0;
let successTimeRemaining = SUCCESS_DURATION;

const containerPosition = computed(() =>
    props.mobileHeaderOffset ? 'top-20 md:top-4' : 'top-4',
);

function clearSuccessTimer(): void {
    if (successTimer !== null) {
        clearTimeout(successTimer);
        successTimer = null;
    }
}

function dismissSuccess(): void {
    clearSuccessTimer();
    successMessage.value = null;
}

function dismissError(): void {
    errorMessage.value = null;
}

function startSuccessTimer(): void {
    clearSuccessTimer();

    if (
        successMessage.value === null ||
        successState.hovered ||
        successState.focused
    ) {
        return;
    }

    successTimerStartedAt = Date.now();
    successTimer = setTimeout(dismissSuccess, successTimeRemaining);
}

function pauseSuccessTimer(): void {
    if (successTimer === null) {
        return;
    }

    successTimeRemaining = Math.max(
        0,
        successTimeRemaining - (Date.now() - successTimerStartedAt),
    );
    clearSuccessTimer();
}

function syncSuccessTimer(): void {
    if (successState.hovered || successState.focused) {
        pauseSuccessTimer();
        return;
    }

    startSuccessTimer();
}

function setSuccessHovered(hovered: boolean): void {
    successState.hovered = hovered;
    syncSuccessTimer();
}

function setSuccessFocused(focused: boolean): void {
    successState.focused = focused;
    syncSuccessTimer();
}

function onSuccessFocusOut(event: FocusEvent): void {
    const currentTarget = event.currentTarget;
    const nextTarget = event.relatedTarget;

    if (
        currentTarget instanceof HTMLElement &&
        nextTarget instanceof Node &&
        currentTarget.contains(nextTarget)
    ) {
        return;
    }

    setSuccessFocused(false);
}

watch(
    flash,
    (value) => {
        successMessage.value =
            typeof value?.success === 'string' ? value.success : null;
        errorMessage.value =
            typeof value?.error === 'string' ? value.error : null;

        successState.hovered = false;
        successState.focused = false;
        successTimeRemaining = SUCCESS_DURATION;

        if (successMessage.value === null) {
            clearSuccessTimer();
        } else {
            startSuccessTimer();
        }
    },
    { immediate: true },
);

onBeforeUnmount(clearSuccessTimer);

const toastStyles: Record<'success' | 'error', string> = {
    success:
        'border-emerald-500/30 bg-surface-container-lowest text-emerald-700',
    error: 'border-error-red/30 bg-surface-container-lowest text-error-red',
};

const toastIcons: Record<'success' | 'error', Component> = {
    success: CircleCheck,
    error: CircleAlert,
};
</script>

<template>
    <TransitionGroup
        name="toast"
        tag="div"
        :class="[
            'pointer-events-none fixed right-4 left-4 z-40 flex flex-col gap-3 sm:left-auto sm:w-full sm:max-w-sm',
            containerPosition,
        ]"
    >
        <div
            v-if="successMessage"
            key="success"
            role="status"
            aria-live="polite"
            aria-atomic="true"
            :class="[
                'pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-xl',
                toastStyles.success,
            ]"
            @mouseenter="setSuccessHovered(true)"
            @mouseleave="setSuccessHovered(false)"
            @focusin="setSuccessFocused(true)"
            @focusout="onSuccessFocusOut"
        >
            <component
                :is="toastIcons.success"
                :size="20"
                class="mt-0.5 shrink-0"
                aria-hidden="true"
            />
            <span class="min-w-0 flex-1 leading-5">{{ successMessage }}</span>
            <button
                type="button"
                class="-m-1 shrink-0 rounded-lg p-1 transition hover:bg-emerald-500/10 focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:outline-none"
                :aria-label="t('common.dismiss_notification')"
                :title="t('common.dismiss_notification')"
                @click="dismissSuccess"
            >
                <X :size="18" aria-hidden="true" />
            </button>
        </div>

        <div
            v-if="errorMessage"
            key="error"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            :class="[
                'pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-xl',
                toastStyles.error,
            ]"
        >
            <component
                :is="toastIcons.error"
                :size="20"
                class="mt-0.5 shrink-0"
                aria-hidden="true"
            />
            <span class="min-w-0 flex-1 leading-5">{{ errorMessage }}</span>
            <button
                type="button"
                class="-m-1 shrink-0 rounded-lg p-1 transition hover:bg-error-red/10 focus-visible:ring-2 focus-visible:ring-error-red focus-visible:outline-none"
                :aria-label="t('common.dismiss_notification')"
                :title="t('common.dismiss_notification')"
                @click="dismissError"
            >
                <X :size="18" aria-hidden="true" />
            </button>
        </div>
    </TransitionGroup>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active,
.toast-move {
    transition:
        transform 0.18s ease,
        opacity 0.18s ease;
}

.toast-enter-from,
.toast-leave-to {
    transform: translateY(-0.5rem);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .toast-enter-active,
    .toast-leave-active,
    .toast-move {
        transition: none;
    }
}
</style>
