<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    LibraryBig,
    Sparkles,
} from '@lucide/vue';

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
    overlayStrength?: number;
}>(), {
    autoplay: true,
    intervalSeconds: 6,
    showArrows: true,
    showDots: true,
    overlayStrength: 42,
});

const activeIndex = ref(0);
const paused = ref(false);
let timer: number | null = null;

const currentSlide = computed(() => props.slides[activeIndex.value] ?? null);
const multipleSlides = computed(() => props.slides.length > 1);
const intervalMs = computed(() => Math.max(4, Math.min(12, props.intervalSeconds)) * 1000);
const overlayOpacity = computed(() => Math.max(0, Math.min(80, props.overlayStrength)) / 100);

function hasImage(slide: HeroSlide | null | undefined) {
    return Boolean(slide?.desktop_url || slide?.mobile_url);
}

function starterSurfaceClass(index: number) {
    return [
        'bg-[#172033]',
        'bg-[#1C3155]',
        'bg-[#173C46]',
    ][index % 3];
}

function stopTimer() {
    if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
    }
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
    if (!props.slides.length) return;

    activeIndex.value = (index + props.slides.length) % props.slides.length;
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

onMounted(() => {
    startTimer();
});

onBeforeUnmount(() => {
    stopTimer();
});
</script>

<template>
    <section
        v-if="slides.length"
        class="ui-page-shell pb-7 pt-5 sm:pb-9 sm:pt-7"
        aria-roledescription="carousel"
        aria-label="Sorotan perpustakaan"
        @mouseenter="pause"
        @mouseleave="resume"
        @focusin="pause"
        @focusout="resume"
        @keydown="onKeydown"
    >
        <div
            class="relative isolate aspect-[4/5] overflow-hidden rounded-[var(--radius-2xl)] border border-line bg-[#172033] shadow-[var(--shadow-float)] md:aspect-[16/7]"
            tabindex="0"
        >
            <div class="absolute inset-0">
                <div
                    v-for="(slide, index) in slides"
                    :key="slide.key"
                    class="absolute inset-0 transition-opacity duration-500"
                    :class="index === activeIndex ? 'opacity-100' : 'pointer-events-none opacity-0'"
                    :aria-hidden="index !== activeIndex"
                >
                    <picture v-if="hasImage(slide)" class="block size-full">
                        <source
                            media="(max-width: 767px)"
                            :srcset="slide.mobile_url || slide.desktop_url || undefined"
                        >
                        <img
                            :src="slide.desktop_url || slide.mobile_url || ''"
                            :alt="slide.alt || slide.title || 'Sorotan perpustakaan'"
                            class="size-full object-cover"
                            :loading="index === 0 ? 'eager' : 'lazy'"
                            :fetchpriority="index === 0 ? 'high' : 'low'"
                            decoding="async"
                        >
                    </picture>

                    <div
                        v-else
                        class="relative size-full overflow-hidden"
                        :class="starterSurfaceClass(index)"
                        aria-hidden="true"
                    >
                        <div class="absolute -right-14 -top-16 size-56 rounded-full border border-white/10 sm:size-72" />
                        <div class="absolute -right-4 top-14 size-40 rounded-full border border-white/10 sm:right-12 sm:size-52" />
                        <div class="absolute bottom-8 right-7 grid size-28 place-items-center rounded-[2rem] border border-white/10 bg-white/[0.07] text-white/80 shadow-2xl backdrop-blur-sm sm:bottom-10 sm:right-14 sm:size-40 lg:right-24 lg:size-44">
                            <BookOpen v-if="index % 3 === 0" class="size-12 sm:size-16" />
                            <LibraryBig v-else-if="index % 3 === 1" class="size-12 sm:size-16" />
                            <Sparkles v-else class="size-12 sm:size-16" />
                        </div>
                        <div class="absolute bottom-0 left-0 h-px w-2/3 bg-white/10" />
                        <div class="absolute left-8 top-8 h-px w-1/3 bg-white/10 sm:left-12 sm:top-12" />
                    </div>
                </div>
            </div>

            <div
                v-if="hasImage(currentSlide)"
                class="absolute inset-0 bg-gradient-to-t from-black via-black/10 to-black/5"
                :style="{ opacity: overlayOpacity }"
                aria-hidden="true"
            />
            <div v-else class="absolute inset-0 bg-black/[0.06]" aria-hidden="true" />

            <div class="absolute inset-x-0 bottom-0 z-10 p-5 sm:p-7 lg:p-10">
                <div class="max-w-2xl">
                    <p class="mb-2.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-white/60 sm:text-xs">
                        Perpustakaan digital
                    </p>

                    <h1
                        v-if="currentSlide?.title"
                        class="text-balance text-3xl font-semibold leading-[1.08] tracking-[-0.03em] text-white drop-shadow-sm sm:text-4xl lg:text-5xl"
                    >
                        {{ currentSlide.title }}
                    </h1>

                    <p
                        v-if="currentSlide?.subtitle"
                        class="mt-3 max-w-xl text-sm leading-6 text-white/80 drop-shadow-sm sm:text-base sm:leading-7"
                    >
                        {{ currentSlide.subtitle }}
                    </p>

                    <Link
                        v-if="currentSlide?.cta_label"
                        :href="currentSlide.cta_href || '/library'"
                        class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-[var(--radius-md)] bg-white px-4 text-sm font-semibold text-[#172033] shadow-sm transition-colors hover:bg-white/90"
                    >
                        {{ currentSlide.cta_label }}
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>

            <div
                v-if="showArrows && multipleSlides"
                class="absolute inset-x-3 top-1/2 z-20 flex -translate-y-1/2 items-center justify-between sm:inset-x-4"
            >
                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-full border border-white/20 bg-black/30 text-white backdrop-blur-sm transition-colors hover:bg-black/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    aria-label="Slide sebelumnya"
                    @click="previous"
                >
                    <ChevronLeft class="size-5" />
                </button>
                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-full border border-white/20 bg-black/30 text-white backdrop-blur-sm transition-colors hover:bg-black/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    aria-label="Slide berikutnya"
                    @click="next"
                >
                    <ChevronRight class="size-5" />
                </button>
            </div>

            <div
                v-if="showDots && multipleSlides"
                class="absolute bottom-3 right-3 z-20 flex items-center gap-1 rounded-full bg-black/25 px-1.5 py-1 backdrop-blur-sm sm:bottom-4 sm:right-4"
                aria-label="Pilih slide"
            >
                <button
                    v-for="(_, index) in slides"
                    :key="index"
                    type="button"
                    class="grid size-8 place-items-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    :aria-label="'Buka slide ' + (index + 1)"
                    :aria-current="index === activeIndex ? 'true' : undefined"
                    @click="goTo(index)"
                >
                    <span
                        class="h-1.5 rounded-full transition-[width,background-color]"
                        :class="index === activeIndex ? 'w-5 bg-white' : 'w-1.5 bg-white/55'"
                    />
                </button>
            </div>
        </div>
    </section>
</template>
