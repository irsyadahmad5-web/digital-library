<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Compass,
    LibraryBig,
    Search,
    Sparkles,
} from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicSearchForm from '@/components/public/PublicSearchForm.vue';
import PublicSectionHeader from '@/components/public/PublicSectionHeader.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SharedPageProps } from '@/types';
import type {
    DirectoryItem,
    HomepageSectionPayload,
    HomepageStatistic,
    PublicBookCard as PublicBook,
} from '@/types/public-library';
import type { SeoPayload } from '@/types/seo';

const props = defineProps<{
    sections: HomepageSectionPayload[];
    seo: SeoPayload;
}>();

const page = usePage<SharedPageProps>();
const general = page.props.site.general;
const hasSearchSection = computed(() => props.sections.some((section) => section.type === 'search'));

const discoveryLinks = [
    { label: 'Katalog', href: '/library' },
    { label: 'Terbaru', href: '/library?sort=newest' },
    { label: 'Kategori', href: '/categories' },
    { label: 'Koleksi', href: '/collections' },
];

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

function statisticsData(section: HomepageSectionPayload) {
    return (section.data ?? []) as HomepageStatistic[];
}

function bookSectionEyebrow(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return 'Paling banyak diakses';
    if (section.type === 'recommendations') return 'Pilihan untuk dijelajahi';

    return 'Baru di perpustakaan';
}

function bookSectionTitle(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return 'Ebook populer';
    if (section.type === 'recommendations') return 'Pilihan perpustakaan';

    return 'Ebook terbaru';
}

function bookSectionHref(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return '/library?sort=popular';
    if (section.type === 'latest_books') return '/library?sort=newest';

    return null;
}

function heroCoverClass(index: number) {
    if (index === 0) return 'left-[6%] top-[16%] z-30 w-[42%] rotate-[-5deg]';
    if (index === 1) return 'left-[34%] top-[4%] z-20 w-[40%] rotate-[4deg]';

    return 'right-[3%] top-[24%] z-10 w-[38%] rotate-[8deg]';
}
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <template v-for="section in sections" :key="section.id">
            <section
                v-if="section.type === 'hero'"
                class="ui-page-shell pb-8 pt-9 sm:pb-10 sm:pt-12 lg:pb-12 lg:pt-14"
            >
                <div
                    class="grid items-center gap-9 lg:gap-12"
                    :class="configBoolean(section, 'show_access_card') && bookData(section).length
                        ? 'lg:grid-cols-[minmax(0,1.06fr)_minmax(360px,.94fr)]'
                        : ''"
                >
                    <div class="min-w-0">
                        <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand">
                            <BookOpen class="size-4" />
                            {{ configString(section, 'eyebrow', String(general.tagline || 'Perpustakaan digital')) }}
                        </div>

                        <h1 class="mt-4 max-w-4xl text-balance text-[2.6rem] font-semibold leading-[1.08] tracking-[-0.035em] text-ink sm:text-5xl lg:text-[3.6rem]">
                            {{ configString(section, 'title', 'Baca lebih nyaman, temukan lebih mudah.') }}
                        </h1>

                        <p
                            v-if="configString(section, 'subtitle')"
                            class="mt-4 max-w-2xl text-base leading-7 text-ink-soft sm:text-[17px]"
                        >
                            {{ configString(section, 'subtitle') }}
                        </p>

                        <div v-if="!hasSearchSection" class="mt-7 max-w-2xl">
                            <PublicSearchForm placeholder="Cari judul, penulis, topik, kategori, atau ISBN…" />
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-x-1 gap-y-2">
                            <span class="mr-1 text-xs font-medium text-ink-faint">Jelajahi:</span>
                            <Link
                                v-for="item in discoveryLinks"
                                :key="item.href"
                                :href="item.href"
                                class="rounded-full px-3 py-1.5 text-xs font-semibold text-ink-soft transition-colors hover:bg-surface-subtle hover:text-ink"
                            >
                                {{ item.label }}
                            </Link>
                        </div>

                        <Link
                            v-if="configString(section, 'cta_label')"
                            :href="configString(section, 'cta_href', '/library')"
                            class="mt-7 inline-flex min-h-10 items-center gap-2 rounded-[var(--radius-md)] bg-brand px-4 text-sm font-semibold text-brand-foreground transition-colors hover:bg-brand-hover"
                        >
                            {{ configString(section, 'cta_label', 'Buka katalog') }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>

                    <div
                        v-if="configBoolean(section, 'show_access_card') && bookData(section).length"
                        class="relative hidden min-h-[360px] overflow-hidden rounded-[var(--radius-xl)] bg-surface-subtle lg:block"
                        aria-label="Pilihan koleksi"
                    >
                        <div class="absolute inset-x-5 bottom-5 flex items-center justify-between gap-4 rounded-[var(--radius-lg)] border border-line/80 bg-surface/95 px-4 py-3 shadow-[var(--shadow-float)] backdrop-blur">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-brand">Pilihan koleksi</p>
                                <p class="mt-1 truncate text-sm font-semibold text-ink">Baca langsung tanpa login</p>
                            </div>
                            <Sparkles class="size-4 shrink-0 text-brand" />
                        </div>

                        <Link
                            v-for="(book, index) in bookData(section).slice(0, 3)"
                            :key="book.id"
                            :href="`/book/${book.slug}`"
                            class="absolute aspect-[3/4] overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface shadow-[var(--shadow-cover)] transition-transform duration-200 hover:z-40 hover:scale-[1.03]"
                            :class="heroCoverClass(index)"
                            :aria-label="book.title"
                        >
                            <img
                                v-if="book.cover_url"
                                :src="book.cover_url"
                                :alt="book.title"
                                class="size-full object-cover"
                                loading="eager"
                                decoding="async"
                            >
                            <div v-else class="flex size-full flex-col items-center justify-center gap-3 bg-brand-soft px-5 text-center text-brand">
                                <BookOpen class="size-8" />
                                <span class="line-clamp-3 text-xs font-semibold leading-5">{{ book.title }}</span>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>

            <section
                v-else-if="section.type === 'search'"
                class="ui-page-shell py-5 sm:py-7"
            >
                <div class="grid gap-5 border-y border-line py-6 md:grid-cols-[minmax(0,.7fr)_minmax(360px,1.3fr)] md:items-center md:gap-8">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand">
                            <Search class="size-4" />
                            Pencarian katalog
                        </div>
                        <h2 class="mt-2 text-balance text-2xl font-semibold tracking-tight text-ink sm:text-[28px]">
                            {{ configString(section, 'title', 'Temukan ebook') }}
                        </h2>
                        <p v-if="configString(section, 'subtitle')" class="mt-2 max-w-xl text-sm leading-6 text-ink-soft">
                            {{ configString(section, 'subtitle') }}
                        </p>
                    </div>
                    <PublicSearchForm
                        :placeholder="configString(section, 'placeholder', 'Cari judul, penulis, topik, kategori, atau ISBN…')"
                    />
                </div>
            </section>

            <section
                v-else-if="['latest_books', 'popular_books', 'recommendations'].includes(section.type) && bookData(section).length"
                class="ui-page-shell py-9 sm:py-11"
            >
                <PublicSectionHeader
                    :eyebrow="configString(section, 'eyebrow', bookSectionEyebrow(section))"
                    :title="configString(section, 'title', bookSectionTitle(section))"
                    :href="bookSectionHref(section) && (section.type !== 'latest_books' || configBoolean(section, 'show_view_all')) ? bookSectionHref(section) : null"
                />

                <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    <PublicBookCard
                        v-for="book in bookData(section)"
                        :key="book.id"
                        :book="book"
                    />
                </div>
            </section>

            <section
                v-else-if="section.type === 'categories' && directoryData(section).length"
                class="ui-page-shell py-9 sm:py-11"
            >
                <PublicSectionHeader
                    :eyebrow="configString(section, 'eyebrow', 'Jelajahi topik')"
                    :title="configString(section, 'title', 'Kategori')"
                    :href="configBoolean(section, 'show_view_all') ? '/categories' : null"
                    link-label="Semua kategori"
                />

                <div class="mt-6 grid border-b border-line sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="item in directoryData(section)"
                        :key="item.slug"
                        :href="`/category/${item.slug}`"
                        class="group flex min-w-0 items-start justify-between gap-4 border-t border-line py-4 pr-3 sm:odd:pr-5 sm:even:pl-5 lg:[&:nth-child(3n+2)]:px-5 lg:[&:nth-child(3n)]:pl-5"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink transition-colors group-hover:text-brand">{{ item.name }}</p>
                            <p v-if="item.parent" class="mt-1 truncate text-xs text-ink-faint">{{ item.parent.name }}</p>
                            <p v-if="item.description" class="mt-1.5 line-clamp-2 text-xs leading-5 text-ink-soft">{{ item.description }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold tabular-nums text-ink-faint">{{ item.count }}</span>
                    </Link>
                </div>
            </section>

            <section
                v-else-if="section.type === 'collections' && directoryData(section).length"
                class="ui-page-shell py-9 sm:py-11"
            >
                <PublicSectionHeader
                    :eyebrow="configString(section, 'eyebrow', 'Pilihan koleksi')"
                    :title="configString(section, 'title', 'Jelajahi koleksi')"
                    :href="configBoolean(section, 'show_view_all') ? '/collections' : null"
                    link-label="Semua koleksi"
                />

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="item in directoryData(section)"
                        :key="item.slug"
                        :href="`/collection/${item.slug}`"
                        class="group min-w-0 rounded-[var(--radius-lg)] bg-surface-subtle p-4 transition-colors hover:bg-brand-soft"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="grid size-9 place-items-center rounded-[var(--radius-md)] bg-surface text-brand">
                                <LibraryBig class="size-4" />
                            </span>
                            <ArrowRight class="size-4 text-ink-faint transition-transform group-hover:translate-x-0.5 group-hover:text-brand" />
                        </div>
                        <p class="mt-4 truncate text-sm font-semibold text-ink group-hover:text-brand">{{ item.name }}</p>
                        <p v-if="item.description" class="mt-1.5 line-clamp-2 text-xs leading-5 text-ink-soft">{{ item.description }}</p>
                        <p class="mt-3 text-[11px] font-medium text-ink-faint">{{ item.count }} ebook</p>
                    </Link>
                </div>
            </section>

            <section
                v-else-if="section.type === 'statistics' && statisticsData(section).length"
                class="my-8 border-y border-line bg-surface sm:my-10"
            >
                <div class="ui-page-shell py-8 sm:py-10">
                    <div class="grid gap-6 lg:grid-cols-[1fr_1.7fr] lg:items-end">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-brand">
                                {{ configString(section, 'eyebrow', 'Perpustakaan dalam angka') }}
                            </p>
                            <h2 class="mt-1.5 text-2xl font-semibold tracking-tight text-ink sm:text-[28px]">
                                {{ configString(section, 'title', 'Statistik') }}
                            </h2>
                        </div>

                        <div class="grid grid-cols-2 divide-x divide-line lg:grid-cols-4">
                            <div v-for="item in statisticsData(section)" :key="item.key" class="px-4 first:pl-0 last:pr-0">
                                <p class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">{{ item.value.toLocaleString('id-ID') }}</p>
                                <p class="mt-1 text-xs text-ink-soft">{{ item.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <section class="ui-page-shell pb-6 pt-4 sm:pb-10">
            <div class="flex flex-col gap-4 rounded-[var(--radius-xl)] bg-brand-soft px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] bg-surface text-brand">
                        <Compass class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">Belum menemukan bacaan yang dicari?</p>
                        <p class="mt-1 text-xs leading-5 text-ink-soft">Jelajahi seluruh katalog, kategori, penulis, penerbit, dan koleksi.</p>
                    </div>
                </div>
                <Link href="/library" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-[var(--radius-md)] bg-brand px-4 text-xs font-semibold text-brand-foreground hover:bg-brand-hover">
                    Buka katalog
                    <ArrowRight class="size-4" />
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
