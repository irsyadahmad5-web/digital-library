<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const reader = page.props.site.reader;
const controlsVisible = ref(true);
let hideTimer: ReturnType<typeof setTimeout> | null = null;

const readerBackground = computed(() => {
    const theme = String(reader.default_theme || 'light');

    if (theme === 'dark') return '#0B1120';
    if (theme === 'sepia') return '#F4ECD8';

    return '#EEF0F4';
});

function scheduleHide() {
    controlsVisible.value = true;

    if (!reader.auto_hide_controls) {
        return;
    }

    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    hideTimer = setTimeout(() => {
        controlsVisible.value = false;
    }, Number(reader.hide_delay_ms || 3500));
}

onBeforeUnmount(() => {
    if (hideTimer) {
        clearTimeout(hideTimer);
    }
});
</script>

<template>
    <div
        class="relative min-h-dvh overflow-hidden text-white"
        :style="{ backgroundColor: readerBackground }"
        @pointermove="scheduleHide"
        @pointerdown="scheduleHide"
    >
        <slot />

        <div
            v-show="controlsVisible"
            class="pointer-events-none absolute inset-x-0 top-0 flex justify-center p-3 transition-opacity sm:p-6"
        >
            <div class="pointer-events-auto w-full max-w-7xl rounded-2xl bg-reader-control/90 px-4 py-3 backdrop-blur">
                <slot name="controls" />
            </div>
        </div>
    </div>
</template>
