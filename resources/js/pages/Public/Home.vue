<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { BookOpen, Search } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const homepage = page.props.site.homepage;
const general = page.props.site.general;
const seo = page.props.site.seo;

const title = computed(() => String(homepage.hero_title || general.tagline || 'Digital Library'));
const subtitle = computed(() => String(homepage.hero_subtitle || general.description || ''));
const placeholderCount = computed(() => Math.min(Number(homepage.latest_limit || 8), 8));
</script>

<template>
    <Head>
        <title>{{ String(seo.title_suffix || general.site_name || 'Digital Library') }}</title>
        <meta name="description" :content="String(seo.meta_description || general.description || '')">
        <meta v-if="seo.robots_index === false" name="robots" content="noindex,nofollow">
        <link v-if="seo.canonical_url" rel="canonical" :href="String(seo.canonical_url)">
        <meta property="og:title" :content="String(seo.og_title || general.site_name || '')">
        <meta property="og:description" :content="String(seo.og_description || general.description || '')">
    </Head>

    <PublicLayout>
        <section class="mx-auto px-5 py-8 sm:px-8 sm:py-12" style="max-width: var(--content-max-width, 1280px)">
            <div class="rounded-3xl border border-border bg-surface px-6 py-14 sm:px-12 sm:py-20 lg:px-16">
                <div class="max-w-3xl">
                    <div class="mb-5 inline-flex items-center gap-2 text-sm font-medium text-primary">
                        <BookOpen class="size-4" />
                        {{ String(general.tagline || 'Perpustakaan digital') }}
                    </div>

                    <h1 class="text-balance text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                        {{ title }}
                    </h1>

                    <p v-if="subtitle" class="mt-5 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
                        {{ subtitle }}
                    </p>

                    <div
                        v-if="homepage.show_search !== false"
                        class="mt-8 flex max-w-2xl items-center gap-3 rounded-2xl border border-border bg-muted px-4 py-3"
                    >
                        <Search class="size-5 shrink-0 text-muted-foreground" />
                        <input
                            class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                            placeholder="Cari judul, penulis, atau kategori..."
                            aria-label="Cari ebook"
                        />
                        <Button size="medium">Cari</Button>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="homepage.show_latest !== false"
            class="mx-auto px-5 pb-16 sm:px-8"
            style="max-width: var(--content-max-width, 1280px)"
        >
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-primary">Koleksi</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">Buku terbaru</h2>
                </div>
                <span class="hidden text-sm text-muted-foreground sm:inline">
                    Katalog ebook akan dihubungkan pada stage berikutnya.
                </span>
            </div>

            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="index in placeholderCount"
                    :key="index"
                    class="rounded-2xl border border-border bg-surface p-3"
                >
                    <div class="aspect-[3/4] rounded-xl bg-muted" />
                    <div class="px-1 pb-2 pt-4">
                        <p class="font-semibold">Contoh Ebook {{ index }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">Penulis ebook</p>
                    </div>
                </article>
            </div>
        </section>
    </PublicLayout>
</template>
