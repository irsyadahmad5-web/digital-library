<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import type {
    PDFDocumentProxy,
    PDFPageProxy,
    RenderTask,
} from 'pdfjs-dist';

const props = defineProps<{
    document: PDFDocumentProxy;
    pageNumber: number;
    active: boolean;
}>();

const emit = defineEmits<{
    select: [pageNumber: number];
}>();

const root = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const pageProxy = shallowRef<PDFPageProxy | null>(null);
const rendered = ref(false);

let observer: IntersectionObserver | null = null;
let renderTask: RenderTask | null = null;

async function renderThumbnail() {
    if (rendered.value || !canvas.value) return;

    try {
        const page = pageProxy.value ?? await props.document.getPage(props.pageNumber);
        pageProxy.value = page;

        const base = page.getViewport({ scale: 1 });
        const scale = Math.min(0.28, 140 / Math.max(1, base.width));
        const viewport = page.getViewport({ scale });
        const outputScale = Math.min(window.devicePixelRatio || 1, 1.5);
        const target = canvas.value;

        target.width = Math.max(1, Math.floor(viewport.width * outputScale));
        target.height = Math.max(1, Math.floor(viewport.height * outputScale));
        target.style.width = `${viewport.width}px`;
        target.style.height = `${viewport.height}px`;

        renderTask = page.render({
            canvas: target,
            viewport,
            transform: outputScale === 1
                ? undefined
                : [outputScale, 0, 0, outputScale, 0, 0],
        });

        await renderTask.promise;
        rendered.value = true;
    } catch (error) {
        if (!(error instanceof Error) || error.name !== 'RenderingCancelledException') {
            rendered.value = false;
        }
    } finally {
        renderTask = null;
    }
}

onMounted(() => {
    if (!root.value) return;

    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry?.isIntersecting) {
                void renderThumbnail();
                observer?.disconnect();
            }
        },
        {
            rootMargin: '500px 0px',
            threshold: 0,
        },
    );

    observer.observe(root.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    renderTask?.cancel();
});
</script>

<template>
    <button
        ref="root"
        type="button"
        class="group flex w-full items-center gap-3 rounded-[var(--radius-lg)] border p-2 text-left transition"
        :class="active
            ? 'border-blue-400/70 bg-blue-500/15'
            : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.06]'"
        :aria-current="active ? 'page' : undefined"
        @click="emit('select', pageNumber)"
    >
        <div class="flex h-[128px] w-[96px] shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white shadow-md">
            <canvas ref="canvas" class="block max-h-full max-w-full" />
        </div>
        <div class="min-w-0">
            <p class="text-xs font-semibold text-white">Halaman {{ pageNumber }}</p>
            <p class="mt-1 text-[11px] text-slate-400">
                {{ active ? 'Sedang dibaca' : 'Buka halaman' }}
            </p>
        </div>
    </button>
</template>
