<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    Maximize2,
    Minus,
    Plus,
    RefreshCw,
    RotateCw,
} from '@lucide/vue';
import {
    GlobalWorkerOptions,
    getDocument,
    type OnProgressParameters,
    type PDFDocumentLoadingTask,
    type PDFDocumentProxy,
} from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import PdfPage from '@/components/reader/PdfPage.vue';
import ReaderLayout from '@/layouts/ReaderLayout.vue';
import type { SharedPageProps } from '@/types';

interface ReaderBook {
    title: string;
    subtitle: string | null;
    slug: string;
    authors: string[];
    page_count: number | null;
}

const props = defineProps<{
    book: ReaderBook;
    sourceUrl: string;
    backUrl: string;
}>();

GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const page = usePage<SharedPageProps>();
const readerSettings = page.props.site.reader;

const scroller = ref<HTMLElement | null>(null);
const pdfDocument = shallowRef<PDFDocumentProxy | null>(null);
const loadingTask = shallowRef<PDFDocumentLoadingTask | null>(null);
const isLoading = ref(true);
const loadError = ref('');
const loadingPercent = ref(0);
const totalPages = ref(Math.max(0, props.book.page_count || 0));
const currentPage = ref(1);
const pageInput = ref('1');
const rotation = ref(0);
const scale = ref(1);
const fitMode = ref<'custom' | 'width' | 'page'>('custom');
const basePageWidth = ref(612);
const basePageHeight = ref(792);
const visibility = new Map<number, number>();

let resizeObserver: ResizeObserver | null = null;

const pageNumbers = computed(() =>
    Array.from({ length: totalPages.value }, (_, index) => index + 1),
);

const zoomLabel = computed(() => `${Math.round(scale.value * 100)}%`);
const pageGap = computed(() =>
    Math.max(0, Math.min(48, Number(readerSettings.page_gap_px || 16))),
);

function clamp(value: number, min: number, max: number) {
    return Math.min(max, Math.max(min, value));
}

async function loadPdf() {
    isLoading.value = true;
    loadError.value = '';
    loadingPercent.value = 0;
    visibility.clear();

    if (loadingTask.value) {
        await loadingTask.value.destroy();
        loadingTask.value = null;
    }

    pdfDocument.value = null;

    try {
        const task = getDocument({
            url: props.sourceUrl,
            rangeChunkSize: 262144,
            disableRange: false,
            disableStream: false,
            disableAutoFetch: false,
        });

        loadingTask.value = task;

        task.onProgress = ({ loaded, total }: OnProgressParameters) => {
            if (total > 0) {
                loadingPercent.value = clamp(
                    Math.round((loaded / total) * 100),
                    0,
                    100,
                );
            }
        };

        const document = await task.promise;
        pdfDocument.value = document;
        totalPages.value = document.numPages;
        currentPage.value = 1;
        pageInput.value = '1';

        const firstPage = await document.getPage(1);
        const viewport = firstPage.getViewport({ scale: 1, rotation: 0 });
        basePageWidth.value = viewport.width;
        basePageHeight.value = viewport.height;

        await nextTick();
        applyInitialScale();
    } catch {
        loadError.value = 'PDF tidak dapat dimuat. Periksa koneksi lalu coba kembali.';
    } finally {
        isLoading.value = false;
    }
}

function applyInitialScale() {
    const preferred = clamp(
        Number(readerSettings.default_zoom || 100) / 100,
        0.5,
        2,
    );
    const fit = fitWidthScale();

    if (preferred > fit) {
        fitMode.value = 'width';
        scale.value = fit;
    } else {
        fitMode.value = 'custom';
        scale.value = preferred;
    }
}

function rotatedDimensions() {
    const normalized = ((rotation.value % 360) + 360) % 360;
    const swaps = normalized === 90 || normalized === 270;

    return {
        width: swaps ? basePageHeight.value : basePageWidth.value,
        height: swaps ? basePageWidth.value : basePageHeight.value,
    };
}

function fitWidthScale() {
    const width = scroller.value?.clientWidth ?? window.innerWidth;
    const pageSize = rotatedDimensions();
    const available = Math.max(220, width - (window.innerWidth < 640 ? 24 : 72));

    return clamp(available / pageSize.width, 0.1, 2);
}

function fitPageScale() {
    const widthScale = fitWidthScale();
    const pageSize = rotatedDimensions();
    const availableHeight = Math.max(280, window.innerHeight - 120);
    const heightScale = availableHeight / pageSize.height;

    return clamp(Math.min(widthScale, heightScale), 0.1, 2);
}

function updateFitScale() {
    if (fitMode.value === 'width') {
        scale.value = fitWidthScale();
    } else if (fitMode.value === 'page') {
        scale.value = fitPageScale();
    }
}

function zoomBy(delta: number) {
    fitMode.value = 'custom';
    scale.value = clamp(
        Math.round((scale.value + delta) * 100) / 100,
        0.5,
        2,
    );
}

function fitWidth() {
    fitMode.value = 'width';
    scale.value = fitWidthScale();
}

function fitPage() {
    fitMode.value = 'page';
    scale.value = fitPageScale();
}

function rotate() {
    rotation.value = (rotation.value + 90) % 360;

    nextTick(() => updateFitScale());
}

function changePage(delta: number) {
    goToPage(currentPage.value + delta);
}

function goToPage(value?: number) {
    const candidate = Number(value ?? pageInput.value);

    if (!Number.isFinite(candidate) || totalPages.value < 1) {
        pageInput.value = String(currentPage.value);

        return;
    }

    const target = clamp(Math.round(candidate), 1, totalPages.value);
    currentPage.value = target;
    pageInput.value = String(target);

    document
        .getElementById(`pdf-page-${target}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function handleVisibility(pageNumber: number, ratio: number) {
    visibility.set(pageNumber, ratio);

    let bestPage = currentPage.value;
    let bestRatio = -1;

    for (const [pageNumberEntry, ratioEntry] of visibility.entries()) {
        if (ratioEntry > bestRatio) {
            bestRatio = ratioEntry;
            bestPage = pageNumberEntry;
        }
    }

    if (bestRatio > 0) {
        currentPage.value = bestPage;
        pageInput.value = String(bestPage);
    }
}

async function toggleFullscreen() {
    if (document.fullscreenElement) {
        await document.exitFullscreen();

        return;
    }

    await document.getElementById('reader-root')?.requestFullscreen();
}

function onResize() {
    updateFitScale();
}

onMounted(() => {
    void loadPdf();

    if (scroller.value) {
        resizeObserver = new ResizeObserver(onResize);
        resizeObserver.observe(scroller.value);
    }

    window.addEventListener('resize', onResize);
});

onBeforeUnmount(async () => {
    resizeObserver?.disconnect();
    window.removeEventListener('resize', onResize);

    if (loadingTask.value) {
        await loadingTask.value.destroy();
    } else if (pdfDocument.value) {
        await pdfDocument.value.cleanup();
    }
});
</script>

<template>
    <Head>
        <title>{{ book.title }}</title>
        <meta name="robots" content="noindex,nofollow">
    </Head>

    <ReaderLayout>
        <div
            ref="scroller"
            class="h-dvh overflow-auto overscroll-contain pt-24 sm:pt-28"
        >
            <div
                v-if="isLoading"
                class="flex min-h-[70dvh] items-center justify-center px-6 text-center"
            >
                <div class="rounded-2xl bg-reader-control/90 px-6 py-5 shadow-xl">
                    <div class="text-sm font-semibold">Memuat PDF…</div>
                    <div class="mt-2 text-xs text-slate-300">
                        {{ loadingPercent > 0 ? `${loadingPercent}%` : 'Menyiapkan dokumen' }}
                    </div>
                </div>
            </div>

            <div
                v-else-if="loadError"
                class="flex min-h-[70dvh] items-center justify-center px-6 text-center"
            >
                <div class="max-w-md rounded-2xl bg-reader-control/95 p-6 shadow-xl">
                    <p class="font-semibold">PDF gagal dimuat</p>
                    <p class="mt-2 text-sm leading-6 text-slate-300">
                        {{ loadError }}
                    </p>
                    <button
                        type="button"
                        class="mt-5 inline-flex min-h-10 items-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-slate-900"
                        @click="loadPdf"
                    >
                        <RefreshCw class="size-4" />
                        Coba lagi
                    </button>
                </div>
            </div>

            <div v-else-if="pdfDocument" class="mx-auto w-full max-w-[1800px]">
                <PdfPage
                    v-for="pageNumber in pageNumbers"
                    :key="pageNumber"
                    :document="pdfDocument"
                    :page-number="pageNumber"
                    :scale="scale"
                    :rotation="rotation"
                    :gap="pageGap"
                    @visibility="handleVisibility"
                />
            </div>
        </div>

        <template #controls>
            <div class="flex min-h-16 flex-wrap items-center gap-2 px-2 py-2 sm:px-3">
                <Link
                    :href="backUrl"
                    class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-200 hover:bg-white/10 hover:text-white"
                    aria-label="Kembali ke detail ebook"
                >
                    <ArrowLeft class="size-5" />
                </Link>

                <div class="hidden min-w-0 flex-1 md:block">
                    <p class="truncate text-sm font-semibold text-white">{{ book.title }}</p>
                    <p v-if="book.authors.length" class="mt-0.5 truncate text-xs text-slate-400">
                        {{ book.authors.join(', ') }}
                    </p>
                </div>

                <div class="ml-auto flex flex-wrap items-center justify-end gap-1.5">
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40"
                        :disabled="currentPage <= 1 || isLoading"
                        aria-label="Halaman sebelumnya"
                        @click="changePage(-1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>

                    <div class="flex h-9 items-center rounded-lg bg-white/10 px-2 text-xs">
                        <input
                            v-model="pageInput"
                            inputmode="numeric"
                            class="w-10 bg-transparent text-center font-semibold text-white outline-none"
                            aria-label="Nomor halaman"
                            @keyup.enter="goToPage()"
                            @blur="goToPage()"
                        >
                        <span class="text-slate-400">/ {{ totalPages || '—' }}</span>
                    </div>

                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40"
                        :disabled="currentPage >= totalPages || isLoading"
                        aria-label="Halaman berikutnya"
                        @click="changePage(1)"
                    >
                        <ChevronRight class="size-4" />
                    </button>

                    <span class="mx-1 hidden h-6 w-px bg-white/15 sm:block" />

                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40"
                        :disabled="isLoading"
                        aria-label="Perkecil"
                        @click="zoomBy(-0.1)"
                    >
                        <Minus class="size-4" />
                    </button>

                    <span class="min-w-12 text-center text-xs font-semibold text-slate-200">
                        {{ zoomLabel }}
                    </span>

                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40"
                        :disabled="isLoading"
                        aria-label="Perbesar"
                        @click="zoomBy(0.1)"
                    >
                        <Plus class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="hidden h-9 items-center rounded-lg px-2.5 text-xs font-medium hover:bg-white/10 sm:inline-flex"
                        :class="fitMode === 'width' ? 'bg-white/15 text-white' : 'text-slate-300'"
                        :disabled="isLoading"
                        @click="fitWidth"
                    >
                        Lebar
                    </button>

                    <button
                        type="button"
                        class="hidden h-9 items-center rounded-lg px-2.5 text-xs font-medium hover:bg-white/10 sm:inline-flex"
                        :class="fitMode === 'page' ? 'bg-white/15 text-white' : 'text-slate-300'"
                        :disabled="isLoading"
                        @click="fitPage"
                    >
                        Halaman
                    </button>

                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40"
                        :disabled="isLoading"
                        aria-label="Putar halaman"
                        @click="rotate"
                    >
                        <RotateCw class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg hover:bg-white/10"
                        aria-label="Layar penuh"
                        @click="toggleFullscreen"
                    >
                        <Maximize2 class="size-4" />
                    </button>
                </div>
            </div>
        </template>
    </ReaderLayout>
</template>
