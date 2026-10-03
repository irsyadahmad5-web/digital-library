<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';

const controlsVisible = ref(true);
let hideTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleHide() {
    controlsVisible.value = true;

    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    hideTimer = setTimeout(() => {
        controlsVisible.value = false;
    }, 3500);
}

onBeforeUnmount(() => {
    if (hideTimer) {
        clearTimeout(hideTimer);
    }
});
</script>

<template>
    <div
        class="relative min-h-dvh overflow-hidden bg-reader-canvas text-white"
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