<script setup lang="ts">
import { computed } from 'vue';

type ReaderTheme = 'light' | 'sepia' | 'dark';

const props = withDefaults(defineProps<{
    theme?: ReaderTheme;
    controlsVisible?: boolean;
}>(), {
    theme: 'light',
    controlsVisible: true,
});

const emit = defineEmits<{
    activity: [];
}>();

const readerBackground = computed(() => {
    if (props.theme === 'dark') return '#0B1120';
    if (props.theme === 'sepia') return '#E8DCC1';

    return '#EEF0F4';
});
</script>

<template>
    <div
        id="reader-root"
        class="relative h-dvh overflow-hidden text-slate-100 transition-colors duration-200"
        :style="{ backgroundColor: readerBackground }"
        @pointermove="emit('activity')"
        @pointerdown="emit('activity')"
        @touchstart.passive="emit('activity')"
        @wheel.passive="emit('activity')"
    >
        <slot />

        <div
            class="reader-controls-safe pointer-events-none absolute inset-x-0 top-0 z-50 flex justify-center transition duration-200"
            :class="controlsVisible
                ? 'translate-y-0 opacity-100'
                : '-translate-y-3 opacity-0'"
            :aria-hidden="!controlsVisible"
            :inert="!controlsVisible"
        >
            <div
                class="w-full max-w-6xl overflow-hidden rounded-[var(--radius-lg)] border border-white/10 bg-reader-control/94 shadow-[0_14px_40px_-20px_rgb(0_0_0/.75)] backdrop-blur-xl"
                :class="controlsVisible ? 'pointer-events-auto' : 'pointer-events-none'"
                @pointermove.stop="emit('activity')"
                @focusin="emit('activity')"
            >
                <slot name="controls" />
            </div>
        </div>

        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 z-50 px-3 pb-[max(.75rem,env(safe-area-inset-bottom))] transition duration-200 sm:hidden"
            :class="controlsVisible
                ? 'translate-y-0 opacity-100'
                : 'translate-y-3 opacity-0'"
            :aria-hidden="!controlsVisible"
            :inert="!controlsVisible"
        >
            <div
                class="pointer-events-auto mx-auto max-w-sm overflow-hidden rounded-[var(--radius-lg)] border border-white/10 bg-reader-control/94 shadow-[0_14px_40px_-20px_rgb(0_0_0/.8)] backdrop-blur-xl"
                @pointermove.stop="emit('activity')"
                @focusin="emit('activity')"
            >
                <slot name="mobile-controls" />
            </div>
        </div>

        <slot name="overlay" />
    </div>
</template>
