<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen } from '@lucide/vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SeoPayload } from '@/types/seo';

defineProps<{
    seo: SeoPayload;
}>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="mx-auto px-5 py-20 sm:px-8 sm:py-28" style="max-width: var(--content-max-width)">
            <div class="mx-auto max-w-xl text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-muted">
                    <BookOpen class="size-6 text-primary" />
                </div>
                <p class="mt-7 text-sm font-semibold text-primary">404</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight">Halaman tidak ditemukan</h1>
                <p class="mt-4 text-base leading-7 text-muted-foreground">
                    Tautan yang Anda buka mungkin sudah berubah, tidak tersedia, atau belum dipublikasikan.
                </p>

                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <Link
                        href="/library"
                        class="inline-flex min-h-11 items-center rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground"
                    >
                        Buka katalog
                    </Link>
                    <Link
                        href="/"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-surface px-5 text-sm font-semibold"
                    >
                        <ArrowLeft class="size-4" />
                        Kembali ke beranda
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
