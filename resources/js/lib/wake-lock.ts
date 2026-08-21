/**
 * Keeps the screen awake during interval shoots. No-ops on browsers
 * without the Screen Wake Lock API.
 */
export function createWakeLock() {
    let sentinel: WakeLockSentinel | null = null;
    let wanted = false;

    async function request(): Promise<void> {
        if (!wanted || !('wakeLock' in navigator)) {
            return;
        }

        try {
            sentinel = await navigator.wakeLock.request('screen');
        } catch {
            // Denied (e.g. low battery); shooting continues without it.
        }
    }

    function handleVisibilityChange(): void {
        if (document.visibilityState === 'visible') {
            void request();
        }
    }

    return {
        async acquire(): Promise<void> {
            wanted = true;
            document.addEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );

            await request();
        },

        release(): void {
            wanted = false;
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );

            void sentinel?.release();
            sentinel = null;
        },
    };
}
