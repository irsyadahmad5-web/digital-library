<script setup lang="ts">
import { X } from '@lucide/vue';

withDefaults(defineProps<{
    open: boolean;
    title: string;
    side?: 'left' | 'right';
}>(), {
    side: 'right',
});

const emit = defineEmits<{
    close: [];
    activity: [];
}>();
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open"
            class="absolute inset-0 z-40"
            @pointermove="emit('activity')"
            @pointerdown="emit('activity')"
        >
            <button
                type="button"
                class="absolute inset-0 bg-slate-950/35 backdrop-blur-[1px]"
                aria-label="Tutup panel"
                @click="emit('close')"
            />

            <section
                class="absolute inset-x-0 bottom-0 flex max-h-[78dvh] flex-col overflow-hidden rounded-t-3xl border border-white/10 bg-slate-950/96 text-slate-100 shadow-2xl md:inset-y-0 md:max-h-none md:w-[380px] md:rounded-none"
                :class="side === 'left' ? 'md:left-0 md:right-auto md:border-r' : 'md:left-auto md:right-0 md:border-l'"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
            >
                <div class="mx-auto mt-2 h-1 w-10 rounded-full bg-white/20 md:hidden" />

                <header class="flex min-h-16 items-center gap-3 border-b border-white/10 px-4">
                    <h2 class="min-w-0 flex-1 truncate text-sm font-semibold text-white">
                        {{ title }}
                    </h2>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center rounded-xl text-slate-300 hover:bg-white/10 hover:text-white"
                        aria-label="Tutup"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    <slot />
                </div>
            </section>
        </div>
    </Transition>
</template>
