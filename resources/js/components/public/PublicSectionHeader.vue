<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

withDefaults(defineProps<{
    eyebrow?: string;
    title: string;
    href?: string | null;
    linkLabel?: string;
}>(), {
    eyebrow: '',
    href: null,
    linkLabel: 'Lihat semua',
});
</script>

<template>
    <div class="flex min-w-0 items-end justify-between gap-2 sm:gap-4">
        <div class="min-w-0">
            <p v-if="eyebrow" class="text-[10px] font-semibold uppercase tracking-[0.12em] text-brand sm:text-xs">
                {{ eyebrow }}
            </p>
            <h2
                class="flex items-center gap-2 text-balance text-[21px] font-semibold leading-tight tracking-tight text-ink sm:gap-2.5 sm:text-[28px]"
                :class="eyebrow ? 'mt-1' : ''"
            >
                <span v-if="$slots.icon" class="grid size-6 shrink-0 place-items-center sm:size-8">
                    <slot name="icon" />
                </span>
                <span>{{ title }}</span>
            </h2>
            <slot />
        </div>
        <Link
            v-if="href"
            :href="href"
            class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-[var(--radius-md)] px-2 text-xs font-semibold text-ink-soft transition-colors hover:bg-surface-subtle hover:text-ink"
        >
            <span class="hidden sm:inline">{{ linkLabel }}</span>
            <ArrowRight class="size-4" />
        </Link>
    </div>
</template>
