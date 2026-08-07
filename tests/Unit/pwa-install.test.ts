import { describe, expect, test, vi } from 'vitest';
import {
    createPwaInstallController,
    detectIosInstallBrowser,
    type BeforeInstallPromptEvent,
    type PwaInstallEnvironment,
} from '@/lib/pwa-install';

function environment(
    overrides: Partial<PwaInstallEnvironment> = {},
): PwaInstallEnvironment & {
    dispatchWindow: (event: Event) => void;
    dispatchDisplayMode: (matches: boolean) => void;
    windowListenerCount: () => number;
    displayModeListenerCount: () => number;
} {
    const windowListeners = new Map<string, Set<EventListener>>();
    const displayModeListeners = new Set<(matches: boolean) => void>();

    return {
        userAgent: 'Mozilla/5.0',
        platform: 'Linux x86_64',
        maxTouchPoints: 0,
        navigatorStandalone: false,
        displayModeStandalone: false,
        addWindowListener(type, listener) {
            const listeners = windowListeners.get(type) ?? new Set();
            listeners.add(listener);
            windowListeners.set(type, listeners);
        },
        removeWindowListener(type, listener) {
            windowListeners.get(type)?.delete(listener);
        },
        addDisplayModeListener(listener) {
            displayModeListeners.add(listener);
        },
        removeDisplayModeListener(listener) {
            displayModeListeners.delete(listener);
        },
        dispatchWindow(event) {
            for (const listener of windowListeners.get(event.type) ?? []) {
                listener(event);
            }
        },
        dispatchDisplayMode(matches) {
            for (const listener of displayModeListeners) listener(matches);
        },
        windowListenerCount: () =>
            [...windowListeners.values()].reduce(
                (total, listeners) => total + listeners.size,
                0,
            ),
        displayModeListenerCount: () => displayModeListeners.size,
        ...overrides,
    };
}

function installPrompt(
    outcome: 'accepted' | 'dismissed',
): BeforeInstallPromptEvent & { prompt: ReturnType<typeof vi.fn> } {
    return Object.assign(
        new Event('beforeinstallprompt', { cancelable: true }),
        {
            prompt: vi.fn(async (): Promise<void> => undefined),
            userChoice: Promise.resolve({ outcome, platform: 'web' }),
        },
    );
}

describe('iOS install browser detection', () => {
    test('distinguishes Safari, Chrome, and another iPhone browser', () => {
        expect(
            detectIosInstallBrowser(
                'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Version/26.0 Mobile/15E148 Safari/604.1',
                'iPhone',
                5,
            ),
        ).toBe('safari');
        expect(
            detectIosInstallBrowser(
                'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 CriOS/140.0 Mobile/15E148 Safari/604.1',
                'iPhone',
                5,
            ),
        ).toBe('chrome');
        expect(
            detectIosInstallBrowser(
                'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 FxiOS/142.0 Mobile/15E148 Safari/605.1.15',
                'iPhone',
                5,
            ),
        ).toBe('other');
    });

    test('recognizes touch-mode iPadOS without treating macOS as iOS', () => {
        expect(
            detectIosInstallBrowser(
                'Mozilla/5.0 (Macintosh) Version/26.0 Safari/605.1.15',
                'MacIntel',
                5,
            ),
        ).toBe('safari');
        expect(
            detectIosInstallBrowser(
                'Mozilla/5.0 (Macintosh) Version/26.0 Safari/605.1.15',
                'MacIntel',
                0,
            ),
        ).toBeNull();
    });
});

describe('PWA install controller', () => {
    test('opens iOS instructions without requesting a native prompt', async () => {
        const env = environment({
            userAgent:
                'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 CriOS/140.0 Mobile/15E148 Safari/604.1',
            platform: 'iPhone',
            maxTouchPoints: 5,
        });
        const controller = createPwaInstallController(env);

        expect(controller.snapshot()).toMatchObject({
            canInstall: true,
            iosBrowser: 'chrome',
            standalone: false,
        });
        await expect(controller.install()).resolves.toBe('instructions');
    });

    test.each(['accepted', 'dismissed'] as const)(
        'consumes a native prompt when the user %s it',
        async (outcome) => {
            const env = environment();
            const controller = createPwaInstallController(env);
            const prompt = installPrompt(outcome);
            controller.start();

            env.dispatchWindow(prompt);

            expect(prompt.defaultPrevented).toBe(true);
            expect(controller.snapshot().canInstall).toBe(true);
            await expect(controller.install()).resolves.toBe(outcome);
            expect(prompt.prompt).toHaveBeenCalledOnce();
            expect(controller.snapshot().canInstall).toBe(false);
        },
    );

    test('hides the action after installation or entering standalone mode', () => {
        const env = environment();
        const controller = createPwaInstallController(env);
        controller.start();
        env.dispatchWindow(installPrompt('accepted'));

        env.dispatchWindow(new Event('appinstalled'));
        expect(controller.snapshot().canInstall).toBe(false);
        expect(controller.snapshot().standalone).toBe(true);

        const displayEnv = environment();
        const displayController = createPwaInstallController(displayEnv);
        displayController.start();
        displayEnv.dispatchWindow(installPrompt('accepted'));
        displayEnv.dispatchDisplayMode(true);
        expect(displayController.snapshot().standalone).toBe(true);
        expect(displayController.snapshot().canInstall).toBe(false);
    });

    test('starts hidden when already standalone and removes every listener', () => {
        const env = environment({ navigatorStandalone: true });
        const controller = createPwaInstallController(env);

        expect(controller.snapshot().canInstall).toBe(false);
        controller.start();
        expect(env.windowListenerCount()).toBe(2);
        expect(env.displayModeListenerCount()).toBe(1);

        controller.stop();
        expect(env.windowListenerCount()).toBe(0);
        expect(env.displayModeListenerCount()).toBe(0);
    });
});
