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
            class="pointer-events-none absolute inset-x-0 top-0 z-50 flex justify-center p-2 transition duration-200 sm:p-4"
            :class="controlsVisible
                ? 'translate-y-0 opacity-100'
                : '-translate-y-3 opacity-0'"
            :aria-hidden="!controlsVisible"
            :inert="!controlsVisible"
        >
            <div
                class="w-full max-w-7xl rounded-2xl bg-reader-control/95 shadow-xl backdrop-blur"
                :class="controlsVisible ? 'pointer-events-auto' : 'pointer-events-none'"
                @pointermove.stop="emit('activity')"
                @focusin="emit('activity')"
            >
                <slot name="controls" />
            </div>
        </div>

        <slot name="overlay" />
    </div>
</template>
