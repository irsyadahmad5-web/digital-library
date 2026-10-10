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
    <div class="flex items-end justify-between gap-4">
        <div class="min-w-0">
            <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.12em] text-brand">
                {{ eyebrow }}
            </p>
            <h2 class="text-balance text-2xl font-semibold tracking-tight text-ink sm:text-[28px]" :class="eyebrow ? 'mt-1.5' : ''">
                {{ title }}
            </h2>
            <slot />
        </div>
        <Link
            v-if="href"
            :href="href"
            class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-[var(--radius-md)] px-2 text-xs font-semibold text-ink-soft transition-colors hover:bg-surface-subtle hover:text-ink"
        >
            <span class="hidden sm:inline">{{ linkLabel }}</span>
            <ArrowRight class="size-4" />
        </Link>
    </div>
</template>
