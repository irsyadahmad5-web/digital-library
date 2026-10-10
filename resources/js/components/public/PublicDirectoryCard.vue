<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import type { DirectoryItem } from '@/types/public-library';

defineProps<{
    item: DirectoryItem;
    href: string;
}>();
</script>

<template>
    <Link
        :href="href"
        class="group flex min-h-32 min-w-0 flex-col justify-between rounded-[var(--radius-lg)] border border-line bg-surface p-4 transition-[border-color,background-color,transform] duration-150 hover:-translate-y-0.5 hover:border-line-strong hover:bg-surface-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/25"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p v-if="item.parent" class="mb-1 truncate text-[11px] font-semibold uppercase tracking-[0.08em] text-brand">
                    {{ item.parent.name }}
                </p>
                <h2 class="line-clamp-2 text-sm font-semibold leading-5 text-ink transition-colors group-hover:text-brand">
                    {{ item.name }}
                </h2>
                <p v-if="item.description" class="mt-1.5 line-clamp-2 text-xs leading-5 text-ink-soft">
                    {{ item.description }}
                </p>
            </div>
            <span class="shrink-0 text-xs font-semibold tabular-nums text-ink-faint">{{ item.count }}</span>
        </div>

        <span class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-brand">
            Lihat ebook
            <ArrowRight class="size-3.5 transition-transform group-hover:translate-x-0.5" />
        </span>
    </Link>
</template>
