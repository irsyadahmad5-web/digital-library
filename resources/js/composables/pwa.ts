import { computed, ref, shallowRef } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
}

const installPrompt = shallowRef<BeforeInstallPromptEvent | null>(null);
const registration = shallowRef<ServiceWorkerRegistration | null>(null);
const updateAvailable = ref(false);
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const standalone = ref(false);
const initialized = ref(false);
let refreshing = false;
let lastUpdateCheck = 0;

function detectStandalone() {
    if (typeof window === 'undefined') return false;

    const navigatorWithStandalone = navigator as Navigator & {
        standalone?: boolean;
    };

    return window.matchMedia('(display-mode: standalone)').matches
        || navigatorWithStandalone.standalone === true;
}

function isIosBrowser() {
    if (typeof navigator === 'undefined') return false;

    return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

function serviceWorkerSupported() {
    if (typeof window === 'undefined') return false;

    return 'serviceWorker' in navigator
        && (
            window.location.protocol === 'https:'
            || ['localhost', '127.0.0.1'].includes(window.location.hostname)
        );
}

async function checkForUpdate(force = false) {
    const current = registration.value;

    if (!current) return;

    const now = Date.now();

    if (!force && now - lastUpdateCheck < 60 * 60 * 1000) {
        return;
    }

    lastUpdateCheck = now;

    try {
        await current.update();
    } catch {
        // Network/update checks are best-effort and must not break the app.
    }
}

export async function initializePwa() {
    if (initialized.value || typeof window === 'undefined') return;

    initialized.value = true;
    standalone.value = detectStandalone();
    online.value = navigator.onLine;

    window.addEventListener('online', () => {
        online.value = true;
        void checkForUpdate();
    });

    window.addEventListener('offline', () => {
        online.value = false;
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        const promptEvent = event as BeforeInstallPromptEvent;

        promptEvent.preventDefault();
        installPrompt.value = promptEvent;
    });

    window.addEventListener('appinstalled', () => {
        installPrompt.value = null;
        standalone.value = true;
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            standalone.value = detectStandalone();
            void checkForUpdate();
        }
    });

    if (!serviceWorkerSupported()) return;

    try {
        const current = await navigator.serviceWorker.register('/sw.js', {
            scope: '/',
            updateViaCache: 'none',
        });

        registration.value = current;

        if (current.waiting && navigator.serviceWorker.controller) {
            updateAvailable.value = true;
        }

        current.addEventListener('updatefound', () => {
            const installing = current.installing;

            if (!installing) return;

            installing.addEventListener('statechange', () => {
                if (
                    installing.state === 'installed'
                    && navigator.serviceWorker.controller
                ) {
                    updateAvailable.value = true;
                }
            });
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshing) return;

            refreshing = true;
            window.location.reload();
        });

        void checkForUpdate(true);
    } catch {
        // PWA enhancement is optional; normal web usage must remain available.
    }
}

export function usePwa() {
    const canPromptInstall = computed(
        () => installPrompt.value !== null && !standalone.value,
    );
    const needsManualIosInstall = computed(
        () => isIosBrowser()
            && !standalone.value
            && installPrompt.value === null,
    );

    async function promptInstall() {
        const prompt = installPrompt.value;

        if (!prompt) return 'unavailable' as const;

        await prompt.prompt();
        const choice = await prompt.userChoice;

        installPrompt.value = null;

        return choice.outcome;
    }

    function applyUpdate() {
        const waiting = registration.value?.waiting;

        if (!waiting) return;

        waiting.postMessage({ type: 'SKIP_WAITING' });
    }

    return {
        online,
        standalone,
        updateAvailable,
        canPromptInstall,
        needsManualIosInstall,
        promptInstall,
        applyUpdate,
        checkForUpdate,
    };
}
