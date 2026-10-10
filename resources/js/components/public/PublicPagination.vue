<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

defineProps<{
    currentPage: number;
    lastPage: number;
    total: number;
    from: number | null;
    to: number | null;
    prevUrl: string | null;
    nextUrl: string | null;
}>();
</script>

<template>
    <nav
        v-if="lastPage > 1 || total > 0"
        aria-label="Pagination katalog"
        class="mt-9 flex flex-col gap-3 border-t border-line pt-4 text-xs sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-ink-soft">
            <template v-if="total">
                {{ from || 0 }}–{{ to || 0 }} dari {{ total }} ebook
            </template>
            <template v-else>
                Belum ada ebook
            </template>
        </p>

        <div v-if="lastPage > 1" class="flex items-center gap-1.5">
            <Link
                v-if="prevUrl"
                :href="prevUrl"
                preserve-scroll
                class="grid size-10 place-items-center rounded-[var(--radius-md)] border border-line bg-surface text-ink-soft transition-colors hover:border-line-strong hover:bg-surface-subtle hover:text-ink"
                aria-label="Halaman sebelumnya"
            >
                <ChevronLeft class="size-4" />
            </Link>
            <span class="min-w-20 rounded-[var(--radius-md)] px-3 py-2.5 text-center font-semibold tabular-nums text-ink-soft">
                {{ currentPage }} / {{ lastPage }}
            </span>
            <Link
                v-if="nextUrl"
                :href="nextUrl"
                preserve-scroll
                class="grid size-10 place-items-center rounded-[var(--radius-md)] border border-line bg-surface text-ink-soft transition-colors hover:border-line-strong hover:bg-surface-subtle hover:text-ink"
                aria-label="Halaman berikutnya"
            >
                <ChevronRight class="size-4" />
            </Link>
        </div>
    </nav>
</template>
