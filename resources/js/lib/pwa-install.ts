export type IosInstallBrowser = 'safari' | 'chrome' | 'other';

export type BeforeInstallPromptChoice = {
    outcome: 'accepted' | 'dismissed';
    platform: string;
};

export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<BeforeInstallPromptChoice>;
};

export type PwaInstallEnvironment = {
    userAgent: string;
    platform: string;
    maxTouchPoints: number;
    navigatorStandalone: boolean;
    displayModeStandalone: boolean;
    addWindowListener: (type: string, listener: EventListener) => void;
    removeWindowListener: (type: string, listener: EventListener) => void;
    addDisplayModeListener: (listener: (matches: boolean) => void) => void;
    removeDisplayModeListener: (listener: (matches: boolean) => void) => void;
};

export type PwaInstallSnapshot = {
    canInstall: boolean;
    iosBrowser: IosInstallBrowser | null;
    standalone: boolean;
};

export type PwaInstallResult =
    | 'instructions'
    | 'accepted'
    | 'dismissed'
    | 'unavailable';

export type PwaInstallController = {
    snapshot: () => PwaInstallSnapshot;
    start: () => void;
    stop: () => void;
    install: () => Promise<PwaInstallResult>;
};

export function detectIosInstallBrowser(
    userAgent: string,
    platform: string,
    maxTouchPoints: number,
): IosInstallBrowser | null {
    const isIos =
        /iPad|iPhone|iPod/i.test(userAgent) ||
        (platform === 'MacIntel' && maxTouchPoints > 1);

    if (!isIos) return null;
    if (/CriOS/i.test(userAgent)) return 'chrome';
    if (
        /Safari/i.test(userAgent) &&
        !/FxiOS|EdgiOS|OPiOS|DuckDuckGo/i.test(userAgent)
    ) {
        return 'safari';
    }

    return 'other';
}

export function createPwaInstallController(
    environment: PwaInstallEnvironment,
    onChange: (snapshot: PwaInstallSnapshot) => void = () => undefined,
): PwaInstallController {
    const iosBrowser = detectIosInstallBrowser(
        environment.userAgent,
        environment.platform,
        environment.maxTouchPoints,
    );
    let standalone =
        environment.navigatorStandalone || environment.displayModeStandalone;
    let deferredPrompt: BeforeInstallPromptEvent | null = null;
    let started = false;

    const snapshot = (): PwaInstallSnapshot => ({
        canInstall:
            !standalone && (iosBrowser !== null || deferredPrompt !== null),
        iosBrowser,
        standalone,
    });
    const notify = (): void => onChange(snapshot());
    const onBeforeInstallPrompt: EventListener = (event) => {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        notify();
    };
    const onAppInstalled: EventListener = () => {
        deferredPrompt = null;
        standalone = true;
        notify();
    };
    const onDisplayModeChange = (matches: boolean): void => {
        standalone = matches || environment.navigatorStandalone;
        notify();
    };

    return {
        snapshot,
        start(): void {
            if (started) return;
            started = true;
            environment.addWindowListener(
                'beforeinstallprompt',
                onBeforeInstallPrompt,
            );
            environment.addWindowListener('appinstalled', onAppInstalled);
            environment.addDisplayModeListener(onDisplayModeChange);
        },
        stop(): void {
            if (!started) return;
            started = false;
            environment.removeWindowListener(
                'beforeinstallprompt',
                onBeforeInstallPrompt,
            );
            environment.removeWindowListener('appinstalled', onAppInstalled);
            environment.removeDisplayModeListener(onDisplayModeChange);
            deferredPrompt = null;
        },
        async install(): Promise<PwaInstallResult> {
            if (standalone) return 'unavailable';
            if (iosBrowser !== null) return 'instructions';
            if (deferredPrompt === null) return 'unavailable';

            const prompt = deferredPrompt;
            deferredPrompt = null;
            notify();

            try {
                await prompt.prompt();
                const choice = await prompt.userChoice;
                return choice.outcome;
            } catch {
                return 'unavailable';
            }
        },
    };
}
