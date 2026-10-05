<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    File,
    Images,
    Maximize2,
    Minimize2,
    Minus,
    Moon,
    MoreHorizontal,
    Plus,
    RefreshCw,
    RotateCw,
    Rows3,
    Search,
    Settings2,
    Sun,
} from '@lucide/vue';
import {
    GlobalWorkerOptions,
    getDocument,
    type OnProgressParameters,
    type PDFDocumentLoadingTask,
    type PDFDocumentProxy,
} from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import PdfPage, { type ReaderTheme } from '@/components/reader/PdfPage.vue';
import PdfThumbnail from '@/components/reader/PdfThumbnail.vue';
import ReaderDrawer from '@/components/reader/ReaderDrawer.vue';
import ReaderLayout from '@/layouts/ReaderLayout.vue';
import type { SharedPageProps } from '@/types';

type ReaderMode = 'continuous' | 'single' | 'book';
type ReaderDrawerName = 'thumbnails' | 'search' | 'settings' | null;

interface ReaderBook {
    title: string;
    subtitle: string | null;
    slug: string;
    authors: string[];
    page_count: number | null;
}

interface SearchResult {
    pageNumber: number;
    preview: string;
    matches: number;
}

const props = defineProps<{
    book: ReaderBook;
    sourceUrl: string;
    backUrl: string;
}>();

GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const page = usePage<SharedPageProps>();
const readerSettings = page.props.site.reader;

function settingBoolean(value: unknown, fallback: boolean) {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value !== 0;

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();

        if (['1', 'true', 'yes', 'on'].includes(normalized)) return true;
        if (['0', 'false', 'no', 'off'].includes(normalized)) return false;
    }

    return fallback;
}

function initialMode(): ReaderMode {
    const configured = String(readerSettings.default_mode || 'continuous');

    if (configured === 'single') return 'single';

    if (
        configured === 'flip'
        && settingBoolean(readerSettings.enable_flip_mode, true)
    ) {
        return 'book';
    }

    return 'continuous';
}

function initialTheme(): ReaderTheme {
    const configured = String(readerSettings.default_theme || 'light');

    if (configured === 'sepia' || configured === 'dark') {
        return configured;
    }

    return 'light';
}

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
const readerMode = ref<ReaderMode>(initialMode());
const readerTheme = ref<ReaderTheme>(initialTheme());
const controlsVisible = ref(true);
const activeDrawer = ref<ReaderDrawerName>(null);
const isBookSpread = ref(false);
const isFullscreen = ref(false);
const visibility = new Map<number, number>();

const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const isSearching = ref(false);
const searchProgress = ref(0);
const textCache = new Map<number, string>();
let searchVersion = 0;

let resizeObserver: ResizeObserver | null = null;
let hideTimer: ReturnType<typeof setTimeout> | null = null;
let touchStart: { x: number; y: number; at: number } | null = null;

const enableFlipMode = computed(() =>
    settingBoolean(readerSettings.enable_flip_mode, true),
);

const autoHideControls = computed(() =>
    settingBoolean(readerSettings.auto_hide_controls, true),
);

const hideDelay = computed(() =>
    Math.max(1000, Math.min(10000, Number(readerSettings.hide_delay_ms || 3500))),
);

const pageNumbers = computed(() =>
    Array.from({ length: totalPages.value }, (_, index) => index + 1),
);

const zoomLabel = computed(() => `${Math.round(scale.value * 100)}%`);

const pageGap = computed(() =>
    Math.max(0, Math.min(48, Number(readerSettings.page_gap_px || 16))),
);

const modeLabel = computed(() => {
    if (readerMode.value === 'single') return 'Single';
    if (readerMode.value === 'book') return 'Book';

    return 'Scroll';
});

const currentSpreadStart = computed(() => {
    if (!isBookSpread.value || currentPage.value <= 1) {
        return currentPage.value;
    }

    return currentPage.value % 2 === 0
        ? currentPage.value
        : currentPage.value - 1;
});

const bookPages = computed(() => {
    if (totalPages.value < 1) return [];

    if (!isBookSpread.value) {
        return [currentPage.value];
    }

    if (currentPage.value <= 1) {
        return [1];
    }

    const start = currentSpreadStart.value;
    const pages = [start];

    if (start + 1 <= totalPages.value) {
        pages.push(start + 1);
    }

    return pages;
});

const bookSpreadKey = computed(() =>
    `${bookPages.value.join('-')}-${rotation.value}-${readerTheme.value}`,
);

function clamp(value: number, min: number, max: number) {
    return Math.min(max, Math.max(min, value));
}

function clearHideTimer() {
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }
}

function scheduleControlsHide() {
    clearHideTimer();

    if (!autoHideControls.value || activeDrawer.value !== null) {
        controlsVisible.value = true;

        return;
    }

    hideTimer = setTimeout(() => {
        if (isTypingTarget(document.activeElement)) {
            scheduleControlsHide();

            return;
        }

        controlsVisible.value = false;
    }, hideDelay.value);
}

function handleActivity() {
    controlsVisible.value = true;
    scheduleControlsHide();
}

function openDrawer(drawer: Exclude<ReaderDrawerName, null>) {
    activeDrawer.value = drawer;
    controlsVisible.value = true;
    clearHideTimer();
}

function closeDrawer() {
    activeDrawer.value = null;
}

async function loadPdf() {
    isLoading.value = true;
    loadError.value = '';
    loadingPercent.value = 0;
    visibility.clear();
    textCache.clear();
    searchResults.value = [];
    searchProgress.value = 0;
    searchVersion++;

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

        const documentProxy = await task.promise;
        pdfDocument.value = documentProxy;
        totalPages.value = documentProxy.numPages;
        currentPage.value = 1;
        pageInput.value = '1';

        const firstPage = await documentProxy.getPage(1);
        const viewport = firstPage.getViewport({ scale: 1, rotation: 0 });
        basePageWidth.value = viewport.width;
        basePageHeight.value = viewport.height;

        await nextTick();
        applyInitialScale();
        handleActivity();
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

    const fit = readerMode.value === 'continuous'
        ? fitWidthScale()
        : fitPageScale();

    if (preferred > fit) {
        fitMode.value = readerMode.value === 'continuous' ? 'width' : 'page';
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
    const outerPadding = window.innerWidth < 640 ? 24 : 72;
    const spreadGap = readerMode.value === 'book' && isBookSpread.value
        ? Math.max(8, pageGap.value)
        : 0;
    const pageMultiplier = readerMode.value === 'book' && isBookSpread.value
        ? 2
        : 1;
    const contentWidth = (pageSize.width * pageMultiplier) + spreadGap;
    const available = Math.max(220, width - outerPadding);

    return clamp(available / Math.max(1, contentWidth), 0.1, 2);
}

function fitPageScale() {
    const widthScale = fitWidthScale();
    const pageSize = rotatedDimensions();
    const availableHeight = Math.max(
        280,
        (scroller.value?.clientHeight ?? window.innerHeight) - 112,
    );
    const heightScale = availableHeight / Math.max(1, pageSize.height);

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
    handleActivity();
}

function fitWidth() {
    fitMode.value = 'width';
    scale.value = fitWidthScale();
    handleActivity();
}

function fitPage() {
    fitMode.value = 'page';
    scale.value = fitPageScale();
    handleActivity();
}

function rotate() {
    rotation.value = (rotation.value + 90) % 360;

    nextTick(() => updateFitScale());
    handleActivity();
}

function setMode(mode: ReaderMode) {
    if (mode === 'book' && !enableFlipMode.value) return;

    readerMode.value = mode;
    visibility.clear();

    nextTick(() => {
        if (mode === 'continuous') {
            if (fitMode.value === 'page') {
                fitMode.value = 'width';
            }
        } else if (fitMode.value === 'width') {
            fitMode.value = 'page';
        }

        updateFitScale();

        if (mode === 'continuous') {
            document
                .getElementById(`pdf-page-${currentPage.value}`)
                ?.scrollIntoView({ block: 'start' });
        }
    });

    handleActivity();
}

function cycleMode() {
    const modes: ReaderMode[] = enableFlipMode.value
        ? ['continuous', 'single', 'book']
        : ['continuous', 'single'];
    const index = modes.indexOf(readerMode.value);

    setMode(modes[(index + 1) % modes.length] ?? 'continuous');
}

function setTheme(theme: ReaderTheme) {
    readerTheme.value = theme;
    handleActivity();
}

function changePage(delta: number) {
    if (readerMode.value === 'book' && isBookSpread.value) {
        const spreadStart = currentSpreadStart.value;
        const target = delta > 0
            ? (spreadStart <= 1 ? 2 : spreadStart + 2)
            : (spreadStart <= 2 ? 1 : spreadStart - 2);

        goToPage(target);

        return;
    }

    goToPage(currentPage.value + delta);
}

function goToPage(value?: number, smooth = true) {
    const candidate = Number(value ?? pageInput.value);

    if (!Number.isFinite(candidate) || totalPages.value < 1) {
        pageInput.value = String(currentPage.value);

        return;
    }

    const target = clamp(Math.round(candidate), 1, totalPages.value);
    currentPage.value = target;
    pageInput.value = String(target);

    if (readerMode.value === 'continuous') {
        nextTick(() => {
            document
                .getElementById(`pdf-page-${target}`)
                ?.scrollIntoView({
                    behavior: smooth ? 'smooth' : 'auto',
                    block: 'start',
                });
        });
    }

    handleActivity();
}

function handleVisibility(pageNumber: number, ratio: number) {
    if (readerMode.value !== 'continuous') return;

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
    try {
        if (document.fullscreenElement) {
            await document.exitFullscreen();

            return;
        }

        await document.getElementById('reader-root')?.requestFullscreen();
    } catch {
        // Fullscreen can be denied by the browser; reader remains usable.
    } finally {
        handleActivity();
    }
}

async function pageText(pageNumber: number) {
    const cached = textCache.get(pageNumber);

    if (cached !== undefined) {
        return cached;
    }

    if (!pdfDocument.value) return '';

    const pageProxy = await pdfDocument.value.getPage(pageNumber);
    const textContent = await pageProxy.getTextContent();
    const text = textContent.items
        .map((item) => {
            const candidate = item as { str?: unknown };

            return typeof candidate.str === 'string' ? candidate.str : '';
        })
        .join(' ')
        .replace(/\s+/g, ' ')
        .trim();

    textCache.set(pageNumber, text);

    return text;
}

function resultPreview(text: string, index: number, queryLength: number) {
    const start = Math.max(0, index - 48);
    const end = Math.min(text.length, index + queryLength + 72);
    const prefix = start > 0 ? '…' : '';
    const suffix = end < text.length ? '…' : '';

    return prefix + text.slice(start, end).trim() + suffix;
}

async function runSearch() {
    const query = searchQuery.value.trim();

    if (!pdfDocument.value || query === '') {
        searchResults.value = [];
        searchProgress.value = 0;

        return;
    }

    const version = ++searchVersion;
    const normalizedQuery = query.toLocaleLowerCase();
    const results: SearchResult[] = [];
    isSearching.value = true;
    searchProgress.value = 0;

    try {
        for (let pageNumber = 1; pageNumber <= totalPages.value; pageNumber++) {
            if (version !== searchVersion) return;

            const text = await pageText(pageNumber);
            const normalizedText = text.toLocaleLowerCase();
            const firstIndex = normalizedText.indexOf(normalizedQuery);

            if (firstIndex >= 0) {
                let matches = 0;
                let cursor = firstIndex;

                while (cursor >= 0) {
                    matches++;
                    cursor = normalizedText.indexOf(
                        normalizedQuery,
                        cursor + Math.max(1, normalizedQuery.length),
                    );
                }

                results.push({
                    pageNumber,
                    preview: resultPreview(text, firstIndex, query.length),
                    matches,
                });
            }

            searchProgress.value = Math.round(
                (pageNumber / Math.max(1, totalPages.value)) * 100,
            );

            if (pageNumber % 8 === 0) {
                await new Promise<void>((resolve) => {
                    window.setTimeout(resolve, 0);
                });
            }
        }

        if (version === searchVersion) {
            searchResults.value = results;
        }
    } catch {
        if (version === searchVersion) {
            searchResults.value = [];
        }
    } finally {
        if (version === searchVersion) {
            isSearching.value = false;
        }
    }
}

function cancelSearch() {
    searchVersion++;
    isSearching.value = false;
    searchProgress.value = 0;
    searchResults.value = [];
}

function openSearch() {
    openDrawer('search');

    nextTick(() => {
        document.getElementById('reader-search-input')?.focus();
    });
}

function selectSearchResult(pageNumber: number) {
    goToPage(pageNumber);
    closeDrawer();
}

function selectThumbnail(pageNumber: number) {
    goToPage(pageNumber);
    closeDrawer();
}

function updateViewportMode() {
    const previous = isBookSpread.value;
    isBookSpread.value = window.innerWidth >= 900;

    if (previous !== isBookSpread.value) {
        nextTick(() => updateFitScale());
    }
}

function onResize() {
    updateViewportMode();
    updateFitScale();
}

function onFullscreenChange() {
    isFullscreen.value = Boolean(document.fullscreenElement);
}

function onWheel(event: WheelEvent) {
    handleActivity();

    if (!event.ctrlKey) return;

    event.preventDefault();
    zoomBy(event.deltaY < 0 ? 0.1 : -0.1);
}

function onTouchStart(event: TouchEvent) {
    handleActivity();

    if (
        readerMode.value === 'continuous'
        || activeDrawer.value !== null
        || event.touches.length !== 1
    ) {
        touchStart = null;

        return;
    }

    const touch = event.touches[0];

    if (!touch) return;

    touchStart = {
        x: touch.clientX,
        y: touch.clientY,
        at: Date.now(),
    };
}

function onTouchEnd(event: TouchEvent) {
    if (!touchStart || readerMode.value === 'continuous') {
        touchStart = null;

        return;
    }

    const touch = event.changedTouches[0];
    const start = touchStart;
    touchStart = null;

    if (!touch) return;

    const dx = touch.clientX - start.x;
    const dy = touch.clientY - start.y;
    const elapsed = Date.now() - start.at;

    if (
        elapsed <= 700
        && Math.abs(dx) >= 55
        && Math.abs(dx) > Math.abs(dy) * 1.25
    ) {
        changePage(dx < 0 ? 1 : -1);
    }
}

function isTypingTarget(target: EventTarget | null) {
    if (!(target instanceof HTMLElement)) return false;

    return Boolean(
        target.closest('input, textarea, select, [contenteditable="true"]'),
    );
}

function onKeydown(event: KeyboardEvent) {
    if (isTypingTarget(event.target)) {
        if (event.key === 'Escape') {
            (event.target as HTMLElement).blur();
        }

        return;
    }

    if (event.ctrlKey || event.metaKey || event.altKey) return;

    const key = event.key.toLowerCase();

    if (key === 'escape' && activeDrawer.value !== null) {
        event.preventDefault();
        closeDrawer();

        return;
    }

    if (key === 'arrowright' || key === 'pagedown') {
        event.preventDefault();
        changePage(1);
    } else if (key === 'arrowleft' || key === 'pageup') {
        event.preventDefault();
        changePage(-1);
    } else if (key === 'home') {
        event.preventDefault();
        goToPage(1);
    } else if (key === 'end') {
        event.preventDefault();
        goToPage(totalPages.value);
    } else if (key === '+' || key === '=') {
        event.preventDefault();
        zoomBy(0.1);
    } else if (key === '-' || key === '_') {
        event.preventDefault();
        zoomBy(-0.1);
    } else if (key === '0') {
        event.preventDefault();
        fitWidth();
    } else if (key === 'r') {
        event.preventDefault();
        rotate();
    } else if (key === 'f') {
        event.preventDefault();
        void toggleFullscreen();
    } else if (key === 't') {
        event.preventDefault();
        openDrawer('thumbnails');
    } else if (key === 's') {
        event.preventDefault();
        openSearch();
    } else if (key === 'm') {
        event.preventDefault();
        cycleMode();
    } else if (key === ',') {
        event.preventDefault();
        openDrawer('settings');
    }

    handleActivity();
}

watch(activeDrawer, (drawer) => {
    if (drawer === null) {
        scheduleControlsHide();
    } else {
        controlsVisible.value = true;
        clearHideTimer();
    }
});

watch(readerMode, () => {
    searchVersion++;
});

onMounted(() => {
    updateViewportMode();
    void loadPdf();

    if (scroller.value) {
        resizeObserver = new ResizeObserver(onResize);
        resizeObserver.observe(scroller.value);
        scroller.value.addEventListener('wheel', onWheel, { passive: false });
        scroller.value.addEventListener('touchstart', onTouchStart, { passive: true });
        scroller.value.addEventListener('touchend', onTouchEnd, { passive: true });
    }

    window.addEventListener('resize', onResize);
    window.addEventListener('keydown', onKeydown);
    document.addEventListener('fullscreenchange', onFullscreenChange);
});

onBeforeUnmount(async () => {
    clearHideTimer();
    cancelSearch();
    resizeObserver?.disconnect();

    if (scroller.value) {
        scroller.value.removeEventListener('wheel', onWheel);
        scroller.value.removeEventListener('touchstart', onTouchStart);
        scroller.value.removeEventListener('touchend', onTouchEnd);
    }

    window.removeEventListener('resize', onResize);
    window.removeEventListener('keydown', onKeydown);
    document.removeEventListener('fullscreenchange', onFullscreenChange);

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

    <ReaderLayout
        :theme="readerTheme"
        :controls-visible="controlsVisible"
        @activity="handleActivity"
    >
        <div
            ref="scroller"
            class="h-dvh overflow-auto overscroll-contain pt-24 sm:pt-28"
            @click.self="handleActivity"
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

            <div
                v-else-if="pdfDocument && readerMode === 'continuous'"
                class="mx-auto w-full max-w-[1800px]"
            >
                <PdfPage
                    v-for="pageNumber in pageNumbers"
                    :key="pageNumber"
                    :document="pdfDocument"
                    :page-number="pageNumber"
                    :scale="scale"
                    :rotation="rotation"
                    :gap="pageGap"
                    :theme="readerTheme"
                    presentation="continuous"
                    @visibility="handleVisibility"
                />
            </div>

            <div
                v-else-if="pdfDocument && readerMode === 'single'"
                class="flex min-h-[calc(100dvh-7rem)] min-w-max items-center justify-center px-4 pb-8"
            >
                <div
                    :key="`single-${currentPage}-${rotation}-${readerTheme}`"
                    class="reader-page-turn"
                >
                    <PdfPage
                        :document="pdfDocument"
                        :page-number="currentPage"
                        :scale="scale"
                        :rotation="rotation"
                        :theme="readerTheme"
                        eager
                        :track-visibility="false"
                        presentation="standalone"
                    />
                </div>
            </div>

            <div
                v-else-if="pdfDocument && readerMode === 'book'"
                class="flex min-h-[calc(100dvh-7rem)] min-w-max items-center justify-center px-4 pb-8"
            >
                <div
                    :key="bookSpreadKey"
                    class="reader-page-turn flex items-center justify-center"
                    :style="{ gap: `${Math.max(8, pageGap)}px` }"
                >
                    <PdfPage
                        v-for="pageNumber in bookPages"
                        :key="pageNumber"
                        :document="pdfDocument"
                        :page-number="pageNumber"
                        :scale="scale"
                        :rotation="rotation"
                        :theme="readerTheme"
                        eager
                        :track-visibility="false"
                        presentation="standalone"
                    />
                </div>
            </div>
        </div>

        <template #controls>
            <div class="flex min-h-16 items-center gap-1.5 px-2 py-2 sm:gap-2 sm:px-3">
                <Link
                    :href="backUrl"
                    class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-200 hover:bg-white/10 hover:text-white"
                    aria-label="Kembali ke detail ebook"
                >
                    <ArrowLeft class="size-5" />
                </Link>

                <div class="hidden min-w-0 flex-1 lg:block">
                    <p class="truncate text-sm font-semibold text-white">{{ book.title }}</p>
                    <p v-if="book.authors.length" class="mt-0.5 truncate text-xs text-slate-400">
                        {{ book.authors.join(', ') }}
                    </p>
                </div>

                <div class="ml-auto flex items-center justify-end gap-1">
                    <button
                        type="button"
                        class="hidden size-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white sm:flex"
                        aria-label="Thumbnail"
                        title="Thumbnail (T)"
                        @click="openDrawer('thumbnails')"
                    >
                        <Images class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="hidden size-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white sm:flex"
                        aria-label="Cari dalam PDF"
                        title="Cari (S)"
                        @click="openSearch"
                    >
                        <Search class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40"
                        :disabled="currentPage <= 1 || isLoading"
                        aria-label="Halaman sebelumnya"
                        @click="changePage(-1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>

                    <div class="flex h-9 items-center rounded-lg bg-white/10 px-1.5 text-xs sm:px-2">
                        <input
                            v-model="pageInput"
                            inputmode="numeric"
                            class="w-8 bg-transparent text-center font-semibold text-white outline-none sm:w-10"
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

                    <span class="mx-1 hidden h-6 w-px bg-white/15 lg:block" />

                    <button
                        type="button"
                        class="hidden size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40 lg:flex"
                        :disabled="isLoading"
                        aria-label="Perkecil"
                        title="Perkecil (-)"
                        @click="zoomBy(-0.1)"
                    >
                        <Minus class="size-4" />
                    </button>

                    <span class="hidden min-w-12 text-center text-xs font-semibold text-slate-200 lg:block">
                        {{ zoomLabel }}
                    </span>

                    <button
                        type="button"
                        class="hidden size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40 lg:flex"
                        :disabled="isLoading"
                        aria-label="Perbesar"
                        title="Perbesar (+)"
                        @click="zoomBy(0.1)"
                    >
                        <Plus class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="hidden h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium text-slate-300 hover:bg-white/10 hover:text-white md:inline-flex"
                        title="Ganti mode (M)"
                        @click="cycleMode"
                    >
                        <Rows3 v-if="readerMode === 'continuous'" class="size-4" />
                        <File v-else-if="readerMode === 'single'" class="size-4" />
                        <BookOpen v-else class="size-4" />
                        {{ modeLabel }}
                    </button>

                    <button
                        type="button"
                        class="hidden size-9 items-center justify-center rounded-lg hover:bg-white/10 disabled:opacity-40 lg:flex"
                        :disabled="isLoading"
                        aria-label="Putar halaman"
                        title="Putar (R)"
                        @click="rotate"
                    >
                        <RotateCw class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="hidden size-9 items-center justify-center rounded-lg hover:bg-white/10 sm:flex"
                        :aria-label="isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'"
                        title="Fullscreen (F)"
                        @click="toggleFullscreen"
                    >
                        <Minimize2 v-if="isFullscreen" class="size-4" />
                        <Maximize2 v-else class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-lg text-slate-200 hover:bg-white/10 hover:text-white"
                        aria-label="Pengaturan reader"
                        title="Pengaturan (,)"
                        @click="openDrawer('settings')"
                    >
                        <MoreHorizontal class="size-5" />
                    </button>
                </div>
            </div>
        </template>

        <template #overlay>
            <ReaderDrawer
                :open="activeDrawer === 'thumbnails'"
                title="Thumbnail halaman"
                side="left"
                @close="closeDrawer"
                @activity="handleActivity"
            >
                <div v-if="pdfDocument" class="grid gap-2 p-3">
                    <PdfThumbnail
                        v-for="pageNumber in pageNumbers"
                        :key="pageNumber"
                        :document="pdfDocument"
                        :page-number="pageNumber"
                        :active="bookPages.includes(pageNumber) && readerMode === 'book'
                            ? true
                            : currentPage === pageNumber"
                        @select="selectThumbnail"
                    />
                </div>
            </ReaderDrawer>

            <ReaderDrawer
                :open="activeDrawer === 'search'"
                title="Cari dalam PDF"
                @close="closeDrawer"
                @activity="handleActivity"
            >
                <div class="p-4">
                    <form class="flex gap-2" @submit.prevent="runSearch">
                        <input
                            id="reader-search-input"
                            v-model="searchQuery"
                            type="search"
                            autocomplete="off"
                            placeholder="Cari kata atau frasa…"
                            class="min-w-0 flex-1 rounded-xl border border-white/10 bg-white/[0.06] px-3 py-2.5 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-400/70"
                            @input="cancelSearch"
                        >
                        <button
                            type="submit"
                            class="rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 disabled:opacity-50"
                            :disabled="isSearching || searchQuery.trim() === ''"
                        >
                            Cari
                        </button>
                    </form>

                    <div v-if="isSearching" class="mt-4">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>Mencari teks…</span>
                            <span>{{ searchProgress }}%</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div
                                class="h-full rounded-full bg-blue-400 transition-[width]"
                                :style="{ width: `${searchProgress}%` }"
                            />
                        </div>
                    </div>

                    <p
                        v-else-if="searchQuery.trim() !== '' && searchResults.length === 0 && searchProgress === 100"
                        class="mt-5 rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-sm text-slate-400"
                    >
                        Teks tidak ditemukan.
                    </p>

                    <div v-if="searchResults.length" class="mt-4 space-y-2">
                        <p class="text-xs text-slate-400">
                            {{ searchResults.length }} halaman ditemukan
                        </p>

                        <button
                            v-for="result in searchResults"
                            :key="result.pageNumber"
                            type="button"
                            class="w-full rounded-2xl border border-white/10 bg-white/[0.03] p-3 text-left hover:border-white/20 hover:bg-white/[0.06]"
                            @click="selectSearchResult(result.pageNumber)"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold text-white">
                                    Halaman {{ result.pageNumber }}
                                </span>
                                <span class="text-[11px] text-slate-500">
                                    {{ result.matches }} cocok
                                </span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-400">
                                {{ result.preview }}
                            </p>
                        </button>
                    </div>
                </div>
            </ReaderDrawer>

            <ReaderDrawer
                :open="activeDrawer === 'settings'"
                title="Pengaturan reader"
                @close="closeDrawer"
                @activity="handleActivity"
            >
                <div class="space-y-6 p-4">
                    <section>
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <Settings2 class="size-3.5" />
                            Mode baca
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                class="rounded-2xl border p-3 text-center text-xs font-semibold transition"
                                :class="readerMode === 'continuous'
                                    ? 'border-blue-400/70 bg-blue-500/15 text-white'
                                    : 'border-white/10 bg-white/[0.03] text-slate-300 hover:bg-white/[0.06]'"
                                @click="setMode('continuous')"
                            >
                                <Rows3 class="mx-auto mb-2 size-5" />
                                Scroll
                            </button>

                            <button
                                type="button"
                                class="rounded-2xl border p-3 text-center text-xs font-semibold transition"
                                :class="readerMode === 'single'
                                    ? 'border-blue-400/70 bg-blue-500/15 text-white'
                                    : 'border-white/10 bg-white/[0.03] text-slate-300 hover:bg-white/[0.06]'"
                                @click="setMode('single')"
                            >
                                <File class="mx-auto mb-2 size-5" />
                                Single
                            </button>

                            <button
                                type="button"
                                class="rounded-2xl border p-3 text-center text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
                                :class="readerMode === 'book'
                                    ? 'border-blue-400/70 bg-blue-500/15 text-white'
                                    : 'border-white/10 bg-white/[0.03] text-slate-300 hover:bg-white/[0.06]'"
                                :disabled="!enableFlipMode"
                                @click="setMode('book')"
                            >
                                <BookOpen class="mx-auto mb-2 size-5" />
                                Book
                            </button>
                        </div>

                        <p v-if="!enableFlipMode" class="mt-2 text-xs text-slate-500">
                            Book mode dinonaktifkan dari Admin Settings.
                        </p>
                    </section>

                    <section>
                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Tema
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                class="rounded-2xl border p-3 text-xs font-semibold"
                                :class="readerTheme === 'light'
                                    ? 'border-blue-400/70 bg-blue-500/15 text-white'
                                    : 'border-white/10 bg-white/[0.03] text-slate-300'"
                                @click="setTheme('light')"
                            >
                                <Sun class="mx-auto mb-2 size-5" />
                                Light
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border p-3 text-xs font-semibold"
                                :class="readerTheme === 'sepia'
                                    ? 'border-amber-300/60 bg-amber-300/10 text-white'
                                    : 'border-white/10 bg-white/[0.03] text-slate-300'"
                                @click="setTheme('sepia')"
                            >
                                <Sun class="mx-auto mb-2 size-5" />
                                Sepia
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border p-3 text-xs font-semibold"
                                :class="readerTheme === 'dark'
                                    ? 'border-blue-400/70 bg-blue-500/15 text-white'
                                    : 'border-white/10 bg-white/[0.03] text-slate-300'"
                                @click="setTheme('dark')"
                            >
                                <Moon class="mx-auto mb-2 size-5" />
                                Dark
                            </button>
                        </div>
                    </section>

                    <section>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Tampilan halaman
                            </span>
                            <span class="text-xs font-semibold text-slate-300">{{ zoomLabel }}</span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-xs font-semibold text-slate-200 hover:bg-white/[0.06]"
                                @click="fitWidth"
                            >
                                Fit width
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-xs font-semibold text-slate-200 hover:bg-white/[0.06]"
                                @click="fitPage"
                            >
                                Fit page
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-xs font-semibold text-slate-200 hover:bg-white/[0.06]"
                                @click="zoomBy(-0.1)"
                            >
                                <Minus class="size-4" />
                                Zoom
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-xs font-semibold text-slate-200 hover:bg-white/[0.06]"
                                @click="zoomBy(0.1)"
                            >
                                <Plus class="size-4" />
                                Zoom
                            </button>
                        </div>
                    </section>

                    <section class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-3 text-xs font-semibold text-slate-200 hover:bg-white/[0.06]"
                            @click="rotate"
                        >
                            <RotateCw class="size-4" />
                            Putar
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-3 text-xs font-semibold text-slate-200 hover:bg-white/[0.06]"
                            @click="toggleFullscreen"
                        >
                            <Minimize2 v-if="isFullscreen" class="size-4" />
                            <Maximize2 v-else class="size-4" />
                            Fullscreen
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-3 text-xs font-semibold text-slate-200 hover:bg-white/[0.06] sm:hidden"
                            @click="activeDrawer = 'thumbnails'"
                        >
                            <Images class="size-4" />
                            Thumbnail
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-3 text-xs font-semibold text-slate-200 hover:bg-white/[0.06] sm:hidden"
                            @click="openSearch"
                        >
                            <Search class="size-4" />
                            Cari
                        </button>
                    </section>

                    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                        <p class="text-xs font-semibold text-white">Shortcut</p>
                        <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-[11px] text-slate-400">
                            <span>← / →</span><span>Halaman</span>
                            <span>+ / −</span><span>Zoom</span>
                            <span>0</span><span>Fit width</span>
                            <span>M</span><span>Mode</span>
                            <span>T / S</span><span>Thumbnail / Cari</span>
                            <span>R / F</span><span>Putar / Fullscreen</span>
                        </div>
                    </section>
                </div>
            </ReaderDrawer>
        </template>
    </ReaderLayout>
</template>
