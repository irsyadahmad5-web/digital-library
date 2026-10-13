import { onBeforeUnmount, onMounted, ref } from 'vue';

export function usePublicOnlineVisitors() {
    const onlineVisitors = ref<number | null>(null);
    let interval: number | null = null;
    let pending = false;
    let active = true;

    async function heartbeat() {
        if (!active || document.visibilityState !== 'visible' || pending) return;

        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (!token) return;

        pending = true;
        try {
            const response = await fetch('/presence/heartbeat', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                onlineVisitors.value = null;
                return;
            }

            const result: { online?: unknown } = await response.json();
            if (active && typeof result.online === 'number' && Number.isFinite(result.online)) {
                onlineVisitors.value = Math.max(0, result.online);
            }
        } catch {
            if (active) onlineVisitors.value = null;
        } finally {
            pending = false;
        }
    }

    onMounted(() => {
        active = true;
        void heartbeat();
        interval = window.setInterval(() => { void heartbeat(); }, 45000);
        document.addEventListener('visibilitychange', heartbeat);
    });

    onBeforeUnmount(() => {
        active = false;
        if (interval !== null) window.clearInterval(interval);
        document.removeEventListener('visibilitychange', heartbeat);
    });

    return { onlineVisitors };
}
