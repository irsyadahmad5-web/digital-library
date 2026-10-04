<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';

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
    <div
        v-if="lastPage > 1 || total > 0"
        class="mt-10 flex flex-col gap-4 border-t border-border pt-5 text-sm sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-muted-foreground">
            <template v-if="total">
                Menampilkan {{ from || 0 }}–{{ to || 0 }} dari {{ total }} ebook
            </template>
            <template v-else>
                Belum ada ebook
            </template>
        </p>

        <div v-if="lastPage > 1" class="flex items-center gap-2">
            <Link
                v-if="prevUrl"
                :href="prevUrl"
                preserve-scroll
                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-border bg-surface px-3 font-medium hover:bg-muted"
            >
                <ArrowLeft class="size-4" />
                Sebelumnya
            </Link>
            <span class="rounded-xl bg-muted px-3 py-2 text-muted-foreground">
                {{ currentPage }} / {{ lastPage }}
            </span>
            <Link
                v-if="nextUrl"
                :href="nextUrl"
                preserve-scroll
                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-border bg-surface px-3 font-medium hover:bg-muted"
            >
                Berikutnya
                <ArrowRight class="size-4" />
            </Link>
        </div>
    </div>
</template>
