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
import type {
    PDFDocumentProxy,
    PDFPageProxy,
    RenderTask,
} from 'pdfjs-dist';

export type ReaderTheme = 'light' | 'sepia' | 'dark';

const props = withDefaults(defineProps<{
    document: PDFDocumentProxy;
    pageNumber: number;
    scale: number;
    rotation: number;
    gap?: number;
    eager?: boolean;
    trackVisibility?: boolean;
    presentation?: 'continuous' | 'standalone';
    theme?: ReaderTheme;
}>(), {
    gap: 0,
    eager: false,
    trackVisibility: true,
    presentation: 'continuous',
    theme: 'light',
});

const emit = defineEmits<{
    visibility: [pageNumber: number, ratio: number];
}>();

const root = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const pageProxy = shallowRef<PDFPageProxy | null>(null);
const isNearViewport = ref(props.eager);
const isRendering = ref(false);
const renderError = ref('');
const renderedWidth = ref<number | null>(null);
const renderedHeight = ref<number | null>(null);

let preloadObserver: IntersectionObserver | null = null;
let visibilityObserver: IntersectionObserver | null = null;
let renderTask: RenderTask | null = null;
let renderVersion = 0;

const pageBackground = computed(() => {
    if (props.theme === 'dark') return '#111827';
    if (props.theme === 'sepia') return '#F7EED8';

    return '#FFFFFF';
});

const pageColors = computed(() => {
    if (props.theme === 'dark') {
        return {
            background: '#111827',
            foreground: '#E5E7EB',
        };
    }

    if (props.theme === 'sepia') {
        return {
            background: '#F7EED8',
            foreground: '#4A3826',
        };
    }

    return undefined;
});

const placeholderStyle = computed(() => {
    if (renderedWidth.value && renderedHeight.value) {
        return {
            width: `${renderedWidth.value}px`,
            height: `${renderedHeight.value}px`,
            backgroundColor: pageBackground.value,
        };
    }

    return {
        width: props.presentation === 'continuous'
            ? 'min(860px, calc(100vw - 24px))'
            : 'min(860px, calc(100vw - 32px))',
        aspectRatio: '1 / 1.4142',
        backgroundColor: pageBackground.value,
    };
});

async function renderPage() {
    if (!isNearViewport.value || !canvas.value) return;

    const version = ++renderVersion;

    if (renderTask) {
        const previous = renderTask;
        previous.cancel();

        try {
            await previous.promise;
        } catch {
            // Cancellation is expected when view settings change.
        }

        if (renderTask === previous) {
            renderTask = null;
        }
    }

    isRendering.value = true;
    renderError.value = '';

    try {
        const page = pageProxy.value ?? await props.document.getPage(props.pageNumber);
        pageProxy.value = page;

        await nextTick();

        if (version !== renderVersion || !canvas.value) return;

        const viewport = page.getViewport({
            scale: props.scale,
            rotation: props.rotation,
        });
        const outputScale = Math.min(window.devicePixelRatio || 1, 2);
        const target = canvas.value;

        renderedWidth.value = viewport.width;
        renderedHeight.value = viewport.height;

        target.width = Math.max(1, Math.floor(viewport.width * outputScale));
        target.height = Math.max(1, Math.floor(viewport.height * outputScale));
        target.style.width = `${viewport.width}px`;
        target.style.height = `${viewport.height}px`;

        const task = page.render({
            canvas: target,
            viewport,
            transform: outputScale === 1
                ? undefined
                : [outputScale, 0, 0, outputScale, 0, 0],
            pageColors: pageColors.value,
        });

        renderTask = task;
        await task.promise;

        if (renderTask === task) {
            renderTask = null;
        }
    } catch (error) {
        const name = error instanceof Error ? error.name : '';

        if (name !== 'RenderingCancelledException') {
            renderError.value = 'Halaman gagal dirender.';
        }
    } finally {
        if (version === renderVersion) {
            isRendering.value = false;
        }
    }
}

onMounted(() => {
    if (!root.value) return;

    if (props.eager) {
        isNearViewport.value = true;
        void renderPage();
    } else {
        preloadObserver = new IntersectionObserver(
            ([entry]) => {
                const near = entry?.isIntersecting ?? false;

                if (near && !isNearViewport.value) {
                    isNearViewport.value = true;
                    void renderPage();
                }
            },
            {
                root: null,
                rootMargin: '1200px 0px',
                threshold: 0,
            },
        );

        preloadObserver.observe(root.value);
    }

    if (props.trackVisibility) {
        visibilityObserver = new IntersectionObserver(
            ([entry]) => {
                emit(
                    'visibility',
                    props.pageNumber,
                    entry?.isIntersecting ? entry.intersectionRatio : 0,
                );
            },
            {
                root: null,
                threshold: [0, 0.1, 0.25, 0.5, 0.75, 1],
            },
        );

        visibilityObserver.observe(root.value);
    }
});

watch(
    () => [props.scale, props.rotation, props.theme],
    () => {
        if (isNearViewport.value) {
            void renderPage();
        }
    },
);

watch(
    () => props.pageNumber,
    () => {
        renderVersion++;
        renderTask?.cancel();
        renderTask = null;
        pageProxy.value = null;
        renderedWidth.value = null;
        renderedHeight.value = null;

        if (props.eager || isNearViewport.value) {
            void renderPage();
        }
    },
);

onBeforeUnmount(() => {
    renderVersion++;
    renderTask?.cancel();
    renderTask = null;
    preloadObserver?.disconnect();
    visibilityObserver?.disconnect();
});
</script>

<template>
    <section
        :id="`pdf-page-${pageNumber}`"
        ref="root"
        :class="presentation === 'continuous'
            ? 'flex w-max min-w-full scroll-mt-24 justify-center px-3 sm:px-6'
            : 'flex shrink-0 items-center justify-center'"
        :style="{ paddingBottom: presentation === 'continuous' ? `${gap}px` : undefined }"
        :aria-label="`Halaman ${pageNumber}`"
    >
        <div
            class="relative overflow-hidden shadow-xl ring-1 ring-black/10 transition-colors"
            :style="placeholderStyle"
        >
            <canvas
                ref="canvas"
                class="block max-w-none"
                :style="{ backgroundColor: pageBackground }"
                :aria-label="`PDF halaman ${pageNumber}`"
            />

            <div
                v-if="isRendering"
                class="pointer-events-none absolute inset-0 flex items-center justify-center text-xs font-medium"
                :class="theme === 'dark'
                    ? 'bg-slate-900/70 text-slate-300'
                    : 'bg-white/65 text-slate-500'"
            >
                Merender halaman {{ pageNumber }}…
            </div>

            <div
                v-if="renderError"
                class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-red-500"
                :style="{ backgroundColor: pageBackground }"
            >
                {{ renderError }}
            </div>
        </div>
    </section>
</template>
