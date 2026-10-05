<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const reader = page.props.site.reader;

const readerBackground = computed(() => {
    const theme = String(reader.default_theme || 'light');

    if (theme === 'dark') return '#0B1120';
    if (theme === 'sepia') return '#F4ECD8';

    return '#EEF0F4';
});
</script>

<template>
    <div
        id="reader-root"
        class="relative h-dvh overflow-hidden text-slate-100"
        :style="{ backgroundColor: readerBackground }"
    >
        <slot />

        <div class="pointer-events-none absolute inset-x-0 top-0 z-50 flex justify-center p-2 sm:p-4">
            <div class="pointer-events-auto w-full max-w-7xl rounded-2xl bg-reader-control/95 shadow-xl backdrop-blur">
                <slot name="controls" />
            </div>
        </div>
    </div>
</template>
