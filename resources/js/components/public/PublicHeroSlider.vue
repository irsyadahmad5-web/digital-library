<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

interface HeroSlide {
    key: string;
    title: string;
    subtitle: string;
    alt: string;
    cta_label: string;
    cta_href: string;
    desktop_url: string | null;
    mobile_url: string | null;
}

const props = withDefaults(defineProps<{
    slides: HeroSlide[];
    autoplay?: boolean;
    intervalSeconds?: number;
    showArrows?: boolean;
    showDots?: boolean;
}>(), {
    autoplay: true,
    intervalSeconds: 6,
    showArrows: true,
    showDots: true,
});

const activeIndex = ref(0);
const paused = ref(false);
const controlsVisible = ref(false);
let timer: number | null = null;
let controlsTimer: number | null = null;

function hasImage(slide: HeroSlide | null | undefined) {
    return Boolean(slide?.desktop_url || slide?.mobile_url);
}

const displaySlides = computed(() => props.slides.filter((slide) => hasImage(slide)));
const multipleSlides = computed(() => displaySlides.value.length > 1);
const intervalMs = computed(() => Math.max(4, Math.min(12, props.intervalSeconds)) * 1000);

function stopTimer() {
    if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
    }
}

function clearControlsTimer() {
    if (controlsTimer !== null) {
        window.clearTimeout(controlsTimer);
        controlsTimer = null;
    }
}

function hideControls() {
    clearControlsTimer();
    controlsVisible.value = false;
}

function revealControls() {
    if (!multipleSlides.value) return;

    controlsVisible.value = true;
    clearControlsTimer();
    controlsTimer = window.setTimeout(() => {
        controlsVisible.value = false;
        controlsTimer = null;
    }, 1400);
}

function startTimer() {
    stopTimer();

    if (
        !props.autoplay
        || paused.value
        || !multipleSlides.value
        || window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        return;
    }

    timer = window.setInterval(() => {
        next();
    }, intervalMs.value);
}

function goTo(index: number) {
    const length = displaySlides.value.length;

    if (!length) return;

    activeIndex.value = (index + length) % length;
    revealControls();
    startTimer();
}

function previous() {
    goTo(activeIndex.value - 1);
}

function next() {
    goTo(activeIndex.value + 1);
}

function pause() {
    paused.value = true;
    stopTimer();
}

function resume() {
    paused.value = false;
    startTimer();
}

function focusIn() {
    pause();
    clearControlsTimer();
    controlsVisible.value = multipleSlides.value;
}

function focusOut() {
    resume();
    revealControls();
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        previous();
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        next();
    }
}

watch(
    () => displaySlides.value.length,
    (length) => {
        if (!length) {
            activeIndex.value = 0;
            stopTimer();
            hideControls();
            return;
        }

        if (activeIndex.value >= length) {
            activeIndex.value = 0;
        }

        startTimer();
    },
);

onMounted(() => {
    startTimer();
});

onBeforeUnmount(() => {
    stopTimer();
    clearControlsTimer();
});
</script>

<template>
    <section
        v-if="displaySlides.length"
        class="w-full overflow-hidden bg-[#172033]"
        aria-roledescription="carousel"
        aria-label="Sorotan perpustakaan"
        @mouseenter="pause"
        @mouseleave="resume"
        @pointermove="revealControls"
        @touchstart.passive="revealControls"
        @focusin="focusIn"
        @focusout="focusOut"
        @keydown="onKeydown"
    >
        <div
            class="relative isolate aspect-[16/9] w-full overflow-hidden bg-[#172033] md:aspect-[8/3]"
            tabindex="0"
        >
            <div class="absolute inset-0">
                <div
                    v-for="(slide, index) in displaySlides"
                    :key="slide.key"
                    class="absolute inset-0 transition-opacity duration-500"
                    :class="index === activeIndex ? 'opacity-100' : 'pointer-events-none opacity-0'"
                    :aria-hidden="index !== activeIndex"
                >
                    <picture class="block size-full">
                        <source
                            media="(max-width: 767px)"
                            :srcset="slide.mobile_url || slide.desktop_url || undefined"
                        >
                        <img
                            :src="slide.desktop_url || slide.mobile_url || ''"
                            :alt="slide.alt || 'Gambar sorotan perpustakaan'"
                            class="size-full object-cover"
                            :loading="index === 0 ? 'eager' : 'lazy'"
                            :fetchpriority="index === 0 ? 'high' : 'low'"
                            decoding="async"
                        >
                    </picture>
                </div>
            </div>

            <div
                v-if="showArrows && multipleSlides"
                class="absolute inset-x-2 top-1/2 z-20 flex -translate-y-1/2 items-center justify-between transition-opacity duration-300 sm:inset-x-3"
                :class="controlsVisible ? 'opacity-100' : 'pointer-events-none opacity-0'"
            >
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-full border border-white/15 bg-black/15 text-white/90 shadow-sm backdrop-blur-sm transition-[background-color,opacity,transform] hover:bg-black/30 hover:text-white active:scale-95 focus-visible:bg-black/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/75 sm:size-11"
                    aria-label="Slide sebelumnya"
                    @click="previous"
                >
                    <ChevronLeft class="size-5" />
                </button>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-full border border-white/15 bg-black/15 text-white/90 shadow-sm backdrop-blur-sm transition-[background-color,opacity,transform] hover:bg-black/30 hover:text-white active:scale-95 focus-visible:bg-black/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/75 sm:size-11"
                    aria-label="Slide berikutnya"
                    @click="next"
                >
                    <ChevronRight class="size-5" />
                </button>
            </div>

            <div
                v-if="showDots && multipleSlides"
                class="absolute bottom-2.5 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1 rounded-full bg-black/10 px-1.5 py-1 backdrop-blur-sm transition-opacity duration-300 sm:bottom-3"
                :class="controlsVisible ? 'opacity-100' : 'pointer-events-none opacity-0'"
                aria-label="Pilih slide"
            >
                <button
                    v-for="(_, index) in displaySlides"
                    :key="index"
                    type="button"
                    class="grid size-7 place-items-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
                    :aria-label="'Buka slide ' + (index + 1)"
                    :aria-current="index === activeIndex ? 'true' : undefined"
                    @click="goTo(index)"
                >
                    <span
                        class="h-1 rounded-full transition-[width,background-color]"
                        :class="index === activeIndex ? 'w-4 bg-white' : 'w-1 bg-white/55'"
                    />
                </button>
            </div>
        </div>
    </section>
</template>
