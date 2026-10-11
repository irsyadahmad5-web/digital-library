<script setup lang="ts">
import { ref } from 'vue';
import {
    Download,
    RefreshCw,
    Share2,
    WifiOff,
    X,
} from '@lucide/vue';
import { usePwa } from '@/composables/pwa';

const {
    online,
    updateAvailable,
    canPromptInstall,
    needsManualIosInstall,
    promptInstall,
    applyUpdate,
} = usePwa();

const iosHelpOpen = ref(false);
const installDismissed = ref(false);

async function install() {
    if (canPromptInstall.value) {
        const outcome = await promptInstall();

        if (outcome === 'dismissed') {
            installDismissed.value = true;
        }

        return;
    }

    if (needsManualIosInstall.value) {
        iosHelpOpen.value = true;
    }
}
</script>

<template>
    <div class="pwa-floating-safe pointer-events-none fixed inset-x-0 bottom-0 z-[80] sm:p-5">
        <div class="mx-auto flex max-w-lg flex-col gap-2">
            <div
                v-if="!online"
                class="pointer-events-auto flex items-start gap-3 rounded-[var(--radius-lg)] border border-warning/25 bg-ink px-4 py-3 text-white shadow-[var(--shadow-float)]"
                role="status"
                aria-live="polite"
            >
                <WifiOff class="mt-0.5 size-4 shrink-0 text-warning" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">Anda sedang offline</p>
                    <p class="mt-1 text-xs leading-5 text-white/70">
                        Halaman yang sedang terbuka tetap dapat digunakan sebisanya.
                        Katalog, PDF, dan data terbaru membutuhkan koneksi internet.
                    </p>
                </div>
            </div>

            <div
                v-if="updateAvailable"
                class="pointer-events-auto flex items-center gap-3 rounded-[var(--radius-lg)] border border-brand/30 bg-ink px-4 py-3 text-white shadow-[var(--shadow-float)]"
                role="status"
                aria-live="polite"
            >
                <RefreshCw class="size-4 shrink-0 text-brand-soft" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">Versi baru tersedia</p>
                    <p class="mt-0.5 text-xs text-white/70">
                        Muat versi terbaru tanpa kehilangan data lokal.
                    </p>
                </div>
                <button
                    type="button"
                    class="min-h-11 shrink-0 rounded-[var(--radius-md)] bg-white px-3 text-xs font-semibold text-ink"
                    @click="applyUpdate"
                >
                    Perbarui
                </button>
            </div>

            <div
                v-if="iosHelpOpen"
                class="pointer-events-auto rounded-[var(--radius-lg)] border border-white/10 bg-ink p-4 text-white shadow-[var(--shadow-float)]"
                role="dialog"
                aria-modal="false"
                aria-label="Cara memasang aplikasi"
            >
                <div class="flex items-start gap-3">
                    <Share2 class="mt-0.5 size-5 shrink-0 text-brand-soft" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">Tambahkan ke Layar Utama</p>
                        <p class="mt-1 text-xs leading-5 text-white/70">
                            Di Safari, tekan tombol Bagikan lalu pilih
                            <strong class="font-semibold text-white">Tambahkan ke Layar Utama</strong>.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="grid size-11 shrink-0 place-items-center rounded-[var(--radius-md)] text-white/75 hover:bg-white/10 hover:text-white"
                        aria-label="Tutup petunjuk instalasi"
                        @click="iosHelpOpen = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <button
                v-if="!installDismissed && (canPromptInstall || needsManualIosInstall)"
                type="button"
                class="pointer-events-auto ml-auto inline-flex min-h-11 items-center gap-2 rounded-[var(--radius-md)] border border-line bg-surface px-4 text-xs font-semibold text-ink shadow-[var(--shadow-float)] hover:bg-surface-subtle"
                @click="install"
            >
                <Download class="size-4" />
                Pasang aplikasi
            </button>
        </div>
    </div>
</template>
