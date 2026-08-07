import { computed, onMounted, onUnmounted, ref, shallowRef } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import {
    createPwaInstallController,
    type IosInstallBrowser,
    type PwaInstallController,
    type PwaInstallEnvironment,
    type PwaInstallSnapshot,
} from '@/lib/pwa-install';

type NavigatorWithStandalone = Navigator & { standalone?: boolean };

function browserEnvironment(): PwaInstallEnvironment | null {
    if (typeof window === 'undefined' || typeof navigator === 'undefined') {
        return null;
    }

    const displayMode = window.matchMedia('(display-mode: standalone)');
    const displayListeners = new Map<
        (matches: boolean) => void,
        (event: MediaQueryListEvent) => void
    >();
    const standaloneNavigator = navigator as NavigatorWithStandalone;

    return {
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        maxTouchPoints: navigator.maxTouchPoints,
        navigatorStandalone: standaloneNavigator.standalone === true,
        displayModeStandalone: displayMode.matches,
        addWindowListener: (type, listener) =>
            window.addEventListener(type, listener),
        removeWindowListener: (type, listener) =>
            window.removeEventListener(type, listener),
        addDisplayModeListener(listener): void {
            const eventListener = (event: MediaQueryListEvent): void =>
                listener(event.matches);
            displayListeners.set(listener, eventListener);
            displayMode.addEventListener('change', eventListener);
        },
        removeDisplayModeListener(listener): void {
            const eventListener = displayListeners.get(listener);
            if (eventListener === undefined) return;
            displayMode.removeEventListener('change', eventListener);
            displayListeners.delete(listener);
        },
    };
}

export function usePwaInstall(): {
    canInstall: ComputedRef<boolean>;
    iosBrowser: ComputedRef<IosInstallBrowser | null>;
    instructionsOpen: Ref<boolean>;
    install: () => Promise<void>;
} {
    const initial: PwaInstallSnapshot = {
        canInstall: false,
        iosBrowser: null,
        standalone: false,
    };
    const state = shallowRef<PwaInstallSnapshot>(initial);
    const instructionsOpen = ref<boolean>(false);
    const environment = browserEnvironment();
    let controller: PwaInstallController | null = null;

    if (environment !== null) {
        controller = createPwaInstallController(environment, (snapshot) => {
            state.value = snapshot;
        });
        state.value = controller.snapshot();
    }

    onMounted(() => controller?.start());
    onUnmounted(() => controller?.stop());

    return {
        canInstall: computed<boolean>(() => state.value.canInstall),
        iosBrowser: computed<IosInstallBrowser | null>(
            () => state.value.iosBrowser,
        ),
        instructionsOpen,
        async install(): Promise<void> {
            if ((await controller?.install()) === 'instructions') {
                instructionsOpen.value = true;
            }
        },
    };
}
