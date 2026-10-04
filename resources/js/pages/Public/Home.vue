<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, LibraryBig } from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicSearchForm from '@/components/public/PublicSearchForm.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SharedPageProps } from '@/types';
import type { DirectoryItem, PublicBookCard as PublicBook } from '@/types/public-library';

const props = defineProps<{
    latestBooks: PublicBook[];
    categories: DirectoryItem[];
}>();

const page = usePage<SharedPageProps>();
const homepage = page.props.site.homepage;
const general = page.props.site.general;
const seo = page.props.site.seo;

const title = computed(() => String(homepage.hero_title || general.tagline || 'Digital Library'));
const subtitle = computed(() => String(homepage.hero_subtitle || general.description || ''));
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
        <section
            class="mx-auto px-5 py-8 sm:px-8 sm:py-12"
            style="max-width: var(--content-max-width)"
        >
            <div class="overflow-hidden rounded-3xl border border-border bg-surface">
                <div class="grid gap-10 px-6 py-12 sm:px-10 sm:py-16 lg:grid-cols-[minmax(0,1.35fr)_minmax(280px,.65fr)] lg:px-14 lg:py-20">
                    <div>
                        <div class="mb-5 inline-flex items-center gap-2 text-sm font-medium text-primary">
                            <BookOpen class="size-4" />
                            {{ String(general.tagline || 'Perpustakaan digital') }}
                        </div>

                        <h1 class="max-w-4xl text-balance text-4xl font-semibold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                            {{ title }}
                        </h1>

                        <p v-if="subtitle" class="mt-5 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
                            {{ subtitle }}
                        </p>

                        <div v-if="homepage.show_search !== false" class="mt-8 max-w-2xl">
                            <PublicSearchForm />
                        </div>
                    </div>

                    <div class="flex items-end">
                        <div class="w-full rounded-2xl bg-muted/70 p-6">
                            <LibraryBig class="size-8 text-primary" />
                            <p class="mt-5 text-sm font-medium text-muted-foreground">Akses publik</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight">
                                Jelajahi koleksi tanpa login
                            </p>
                            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                                Temukan ebook melalui judul, penulis, kategori, penerbit, ISBN, atau koleksi.
                            </p>
                            <Link
                                href="/library"
                                class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
                            >
                                Buka katalog
                                <ArrowRight class="size-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="homepage.show_latest !== false && props.latestBooks.length"
            class="mx-auto px-5 py-8 sm:px-8"
            style="max-width: var(--content-max-width)"
        >
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-primary">Koleksi terbaru</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Ebook terbaru</h2>
                </div>
                <Link href="/library" class="hidden items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground sm:inline-flex">
                    Lihat semua
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <div class="mt-7 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <PublicBookCard
                    v-for="book in props.latestBooks"
                    :key="book.id"
                    :book="book"
                />
            </div>
        </section>

        <section
            v-if="homepage.show_categories !== false && props.categories.length"
            class="mx-auto px-5 py-10 sm:px-8"
            style="max-width: var(--content-max-width)"
        >
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-primary">Jelajahi topik</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Kategori</h2>
                </div>
                <Link href="/categories" class="hidden items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground sm:inline-flex">
                    Semua kategori
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <Link
                    v-for="category in props.categories"
                    :key="category.slug"
                    :href="`/category/${category.slug}`"
                    class="group rounded-2xl border border-border bg-surface p-5 transition-colors hover:bg-muted/50"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold group-hover:text-primary">{{ category.name }}</p>
                            <p v-if="category.parent" class="mt-1 text-xs text-muted-foreground">
                                {{ category.parent.name }}
                            </p>
                            <p v-if="category.description" class="mt-3 line-clamp-2 text-sm leading-6 text-muted-foreground">
                                {{ category.description }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                            {{ category.count }}
                        </span>
                    </div>
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
