<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, LibraryBig, Search } from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicSearchForm from '@/components/public/PublicSearchForm.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SharedPageProps } from '@/types';
import type {
    DirectoryItem,
    HomepageSectionPayload,
    PublicBookCard as PublicBook,
} from '@/types/public-library';

defineProps<{
    sections: HomepageSectionPayload[];
}>();

const page = usePage<SharedPageProps>();
const general = page.props.site.general;
const seo = page.props.site.seo;

function configString(section: HomepageSectionPayload, key: string, fallback = '') {
    const value = section.config[key];

    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}

function configBoolean(section: HomepageSectionPayload, key: string, fallback = true) {
    const value = section.config[key];

    return typeof value === 'boolean' ? value : fallback;
}

function bookData(section: HomepageSectionPayload) {
    return (section.data ?? []) as PublicBook[];
}

function directoryData(section: HomepageSectionPayload) {
    return (section.data ?? []) as DirectoryItem[];
}

function bookSectionEyebrow(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return 'Paling banyak diunduh';
    if (section.type === 'recommendations') return 'Untuk dijelajahi';

    return 'Koleksi terbaru';
}

function bookSectionTitle(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return 'Ebook populer';
    if (section.type === 'recommendations') return 'Rekomendasi';

    return 'Ebook terbaru';
}

function bookSectionHref(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return '/library?sort=popular';
    if (section.type === 'latest_books') return '/library';

    return null;
}
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
        <template v-for="section in sections" :key="section.id">
            <section
                v-if="section.type === 'hero'"
                class="mx-auto px-5 py-8 sm:px-8 sm:py-12"
                style="max-width: var(--content-max-width)"
            >
                <div class="overflow-hidden rounded-3xl border border-border bg-surface">
                    <div
                        class="grid gap-10 px-6 py-12 sm:px-10 sm:py-16 lg:px-14 lg:py-20"
                        :class="configBoolean(section, 'show_access_card')
                            ? 'lg:grid-cols-[minmax(0,1.35fr)_minmax(280px,.65fr)]'
                            : ''"
                    >
                        <div>
                            <div class="mb-5 inline-flex items-center gap-2 text-sm font-medium text-primary">
                                <BookOpen class="size-4" />
                                {{ configString(section, 'eyebrow', String(general.tagline || 'Perpustakaan digital')) }}
                            </div>

                            <h1 class="max-w-4xl text-balance text-4xl font-semibold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                                {{ configString(section, 'title', 'Digital Library') }}
                            </h1>

                            <p
                                v-if="configString(section, 'subtitle')"
                                class="mt-5 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg"
                            >
                                {{ configString(section, 'subtitle') }}
                            </p>

                            <Link
                                v-if="configString(section, 'cta_label')"
                                :href="configString(section, 'cta_href', '/library')"
                                class="mt-8 inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground"
                            >
                                {{ configString(section, 'cta_label', 'Buka katalog') }}
                                <ArrowRight class="size-4" />
                            </Link>
                        </div>

                        <div v-if="configBoolean(section, 'show_access_card')" class="flex items-end">
                            <div class="w-full rounded-2xl bg-muted/70 p-6">
                                <LibraryBig class="size-8 text-primary" />
                                <p class="mt-5 text-sm font-medium text-muted-foreground">Akses publik</p>
                                <p class="mt-2 text-2xl font-semibold tracking-tight">
                                    Jelajahi koleksi tanpa login
                                </p>
                                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                                    Temukan ebook melalui judul, penulis, kategori, penerbit, ISBN, atau koleksi.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-else-if="section.type === 'search'"
                class="mx-auto px-5 py-8 sm:px-8"
                style="max-width: var(--content-max-width)"
            >
                <div class="rounded-3xl border border-border bg-surface px-6 py-8 sm:px-10 sm:py-10">
                    <div class="max-w-3xl">
                        <div class="flex items-center gap-2 text-sm font-medium text-primary">
                            <Search class="size-4" />
                            Pencarian katalog
                        </div>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                            {{ configString(section, 'title', 'Temukan ebook') }}
                        </h2>
                        <p
                            v-if="configString(section, 'subtitle')"
                            class="mt-3 text-sm leading-6 text-muted-foreground sm:text-base"
                        >
                            {{ configString(section, 'subtitle') }}
                        </p>
                    </div>

                    <div class="mt-6 max-w-3xl">
                        <PublicSearchForm
                            :placeholder="configString(section, 'placeholder', 'Cari judul, penulis, kategori, penerbit, atau ISBN...')"
                        />
                    </div>
                </div>
            </section>

            <section
                v-else-if="['latest_books', 'popular_books', 'recommendations'].includes(section.type) && bookData(section).length"
                class="mx-auto px-5 py-8 sm:px-8"
                style="max-width: var(--content-max-width)"
            >
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-primary">
                            {{ configString(section, 'eyebrow', bookSectionEyebrow(section)) }}
                        </p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                            {{ configString(section, 'title', bookSectionTitle(section)) }}
                        </h2>
                    </div>
                    <Link
                        v-if="bookSectionHref(section) && (section.type !== 'latest_books' || configBoolean(section, 'show_view_all'))"
                        :href="bookSectionHref(section)!"
                        class="hidden items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground sm:inline-flex"
                    >
                        Lihat semua
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <div class="mt-7 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    <PublicBookCard
                        v-for="book in bookData(section)"
                        :key="book.id"
                        :book="book"
                    />
                </div>
            </section>

            <section
                v-else-if="section.type === 'categories' && directoryData(section).length"
                class="mx-auto px-5 py-10 sm:px-8"
                style="max-width: var(--content-max-width)"
            >
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-primary">
                            {{ configString(section, 'eyebrow', 'Jelajahi topik') }}
                        </p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                            {{ configString(section, 'title', 'Kategori') }}
                        </h2>
                    </div>
                    <Link
                        v-if="configBoolean(section, 'show_view_all')"
                        href="/categories"
                        class="hidden items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground sm:inline-flex"
                    >
                        Semua kategori
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Link
                        v-for="item in directoryData(section)"
                        :key="item.slug"
                        :href="`/category/${item.slug}`"
                        class="group rounded-2xl border border-border bg-surface p-5 transition-colors hover:bg-muted/50"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-semibold group-hover:text-primary">{{ item.name }}</p>
                                <p v-if="item.parent" class="mt-1 text-xs text-muted-foreground">
                                    {{ item.parent.name }}
                                </p>
                                <p v-if="item.description" class="mt-3 line-clamp-2 text-sm leading-6 text-muted-foreground">
                                    {{ item.description }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                {{ item.count }}
                            </span>
                        </div>
                    </Link>
                </div>
            </section>

            <section
                v-else-if="section.type === 'collections' && directoryData(section).length"
                class="mx-auto px-5 py-10 sm:px-8"
                style="max-width: var(--content-max-width)"
            >
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-primary">
                            {{ configString(section, 'eyebrow', 'Pilihan koleksi') }}
                        </p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                            {{ configString(section, 'title', 'Jelajahi koleksi') }}
                        </h2>
                    </div>
                    <Link
                        v-if="configBoolean(section, 'show_view_all')"
                        href="/collections"
                        class="hidden items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground sm:inline-flex"
                    >
                        Semua koleksi
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="item in directoryData(section)"
                        :key="item.slug"
                        :href="`/collection/${item.slug}`"
                        class="group rounded-2xl border border-border bg-surface p-5 transition-colors hover:bg-muted/50"
                    >
                        <LibraryBig class="size-5 text-primary" />
                        <p class="mt-4 font-semibold group-hover:text-primary">{{ item.name }}</p>
                        <p v-if="item.description" class="mt-2 line-clamp-2 text-sm leading-6 text-muted-foreground">
                            {{ item.description }}
                        </p>
                        <p class="mt-4 text-xs font-medium text-muted-foreground">
                            {{ item.count }} ebook
                        </p>
                    </Link>
                </div>
            </section>
        </template>
    </PublicLayout>
</template>
