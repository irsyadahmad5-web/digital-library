<script setup lang="ts">
import { ref } from 'vue';
import { Download, RefreshCw, Share2, WifiOff, X } from '@lucide/vue';
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
        <div class="mx-auto flex max-w-xl flex-col gap-2">
            <div
                v-if="!online"
                class="pointer-events-auto flex items-start gap-3 rounded-2xl border border-amber-300/25 bg-slate-950/95 px-4 py-3 text-slate-100 shadow-2xl backdrop-blur"
                role="status"
            >
                <WifiOff class="mt-0.5 size-4 shrink-0 text-amber-300" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">Anda sedang offline</p>
                    <p class="mt-1 text-xs leading-5 text-slate-400">
                        Halaman yang sedang terbuka tetap dapat digunakan sebisanya.
                        Katalog, PDF, dan data terbaru membutuhkan koneksi internet.
                    </p>
                </div>
            </div>

            <div
                v-if="updateAvailable"
                class="pointer-events-auto flex items-center gap-3 rounded-2xl border border-blue-300/20 bg-slate-950/95 px-4 py-3 text-slate-100 shadow-2xl backdrop-blur"
                role="status"
            >
                <RefreshCw class="size-4 shrink-0 text-blue-300" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold">Versi baru tersedia</p>
                    <p class="mt-0.5 text-xs text-slate-400">
                        Muat versi terbaru tanpa kehilangan data akun.
                    </p>
                </div>
                <button
                    type="button"
                    class="min-h-11 shrink-0 rounded-xl bg-white px-3 text-xs font-semibold text-slate-950"
                    @click="applyUpdate"
                >
                    Perbarui
                </button>
            </div>

            <div
                v-if="iosHelpOpen"
                class="pointer-events-auto rounded-2xl border border-white/10 bg-slate-950/95 p-4 text-slate-100 shadow-2xl backdrop-blur"
                role="dialog"
                aria-modal="false"
                aria-label="Cara memasang aplikasi"
            >
                <div class="flex items-start gap-3">
                    <Share2 class="mt-0.5 size-5 shrink-0 text-blue-300" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">Tambahkan ke Layar Utama</p>
                        <p class="mt-1 text-xs leading-5 text-slate-400">
                            Di Safari, tekan tombol Bagikan lalu pilih
                            <strong class="font-semibold text-slate-200">Tambahkan ke Layar Utama</strong>.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl hover:bg-white/10"
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
                class="pointer-events-auto ml-auto inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-surface px-4 text-xs font-semibold text-foreground shadow-lg hover:bg-muted"
                @click="install"
            >
                <Download class="size-4" />
                Pasang aplikasi
            </button>
        </div>
    </div>
</template>
