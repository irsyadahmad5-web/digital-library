<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Search } from '@lucide/vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Button } from '@/components/ui/button';
import type { SeoPayload } from '@/types/seo';

defineProps<{
    seo: SeoPayload;
}>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="ui-page-shell flex min-h-[62vh] items-center justify-center py-14 sm:py-20">
            <div class="w-full max-w-xl text-center">
                <span class="mx-auto grid size-11 place-items-center rounded-[var(--radius-lg)] bg-brand-soft text-brand">
                    <BookOpen class="size-5" />
                </span>

                <p class="mt-5 text-[11px] font-semibold uppercase tracking-[0.14em] text-brand">404 · Tidak ditemukan</p>
                <h1 class="mt-2 text-balance text-3xl font-semibold tracking-[-0.03em] text-ink sm:text-4xl">
                    Halaman ini tidak tersedia
                </h1>
                <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-ink-soft">
                    Tautan mungkin sudah berubah, belum dipublikasikan, atau tidak lagi tersedia. Anda tetap dapat melanjutkan dari katalog.
                </p>

                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <Button as-child>
                        <Link href="/library">
                            <Search class="size-4" />
                            Buka katalog
                        </Link>
                    </Button>
                    <Button as-child variant="secondary">
                        <Link href="/">
                            <ArrowLeft class="size-4" />
                            Kembali ke beranda
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
