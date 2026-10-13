<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Compass,
    Flame,
    LibraryBig,
    Search,
    Star,
} from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import CategoryIcon from '@/components/public/CategoryIcon.vue';
import PublicHeroSlider from '@/components/public/PublicHeroSlider.vue';
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

interface HeroSlide {
    key: string;
    title: string;
    subtitle: string;
    alt: string;
    cta_label: string;
    cta_href: string;
    desktop_url: string | null;
    mobile_url: string | null;
}

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

function heroSlides(section: HomepageSectionPayload): HeroSlide[] {
    const value = section.config.slides;

    if (!Array.isArray(value)) return [];

    return (value as HeroSlide[]).filter((slide) =>
        Boolean(slide?.desktop_url || slide?.mobile_url),
    );
}

function configNumber(section: HomepageSectionPayload, key: string, fallback: number) {
    const value = section.config[key];

    return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

function bookSectionEyebrow(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return 'Paling banyak dibaca';
    if (section.type === 'recommendations') return 'Pilihan untuk dijelajahi';

    return 'Baru di perpustakaan';
}

function bookSectionTitle(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return 'Buku Populer';
    if (section.type === 'recommendations') return 'Pilihan perpustakaan';

    return 'Buku Terbaru';
}

function bookSectionHref(section: HomepageSectionPayload) {
    if (section.type === 'popular_books') return '/library?sort=popular';
    if (section.type === 'latest_books') return '/library?sort=newest';

    return null;
}

</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <template v-for="section in sections" :key="section.id">
            <PublicHeroSlider
                v-if="section.type === 'hero' && heroSlides(section).length"
                :slides="heroSlides(section)"
                :autoplay="configBoolean(section, 'autoplay')"
                :interval-seconds="configNumber(section, 'interval_seconds', 6)"
                :show-arrows="configBoolean(section, 'show_arrows')"
                :show-dots="configBoolean(section, 'show_dots')"
            />

            <section
                v-else-if="section.type === 'hero'"
                class="ui-page-shell pb-8 pt-9 sm:pb-10 sm:pt-12 lg:pb-12 lg:pt-14"
            >
                <div class="max-w-4xl">
                    <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand">
                        <BookOpen class="size-4" />
                        {{ String(general.tagline || 'Perpustakaan digital') }}
                    </div>

                    <h1 class="mt-4 text-balance text-[2.6rem] font-semibold leading-[1.08] tracking-[-0.035em] text-ink sm:text-5xl lg:text-[3.6rem]">
                        Baca lebih nyaman, temukan lebih mudah.
                    </h1>

                    <p class="mt-4 max-w-2xl text-base leading-7 text-ink-soft sm:text-[17px]">
                        Jelajahi koleksi buku, baca langsung di browser, atau unduh PDF tanpa harus membuat akun.
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
                        href="/library"
                        class="mt-7 inline-flex min-h-10 items-center gap-2 rounded-[var(--radius-md)] bg-brand px-4 text-sm font-semibold text-brand-foreground transition-colors hover:bg-brand-hover"
                    >
                        Buka katalog
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </section>

            <section
                v-else-if="section.type === 'search'"
                class="ui-page-shell py-4 sm:py-7"
            >
                <div class="grid gap-3 border-y border-line py-4 sm:gap-5 sm:py-6 md:grid-cols-[minmax(0,.7fr)_minmax(360px,1.3fr)] md:items-center md:gap-8">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-brand">
                            <Search class="size-4" />
                            Pencarian katalog
                        </div>
                        <h2 class="mt-2 text-balance text-2xl font-semibold tracking-tight text-ink sm:text-[28px]">
                            {{ configString(section, 'title', 'Temukan buku') }}
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
                class="ui-page-shell py-3 sm:py-8 lg:py-9"
            >
                <PublicSectionHeader
                    compact-mobile
                    :eyebrow="configString(section, 'eyebrow', bookSectionEyebrow(section))"
                    :title="configString(section, 'title', bookSectionTitle(section))"
                    :href="bookSectionHref(section) && configBoolean(section, 'show_view_all') ? bookSectionHref(section) : null"
                >
                    <template #icon>
                        <Star v-if="section.type === 'latest_books'" class="size-5 fill-current text-warning sm:size-7" />
                        <Flame v-else-if="section.type === 'popular_books'" class="size-5 fill-current text-danger sm:size-7" />
                    </template>
                </PublicSectionHeader>

                <div class="mt-2.5 grid grid-cols-3 gap-2 sm:mt-5 sm:gap-x-4 sm:gap-y-5 lg:grid-cols-4 xl:grid-cols-6">
                    <PublicBookCard
                        v-for="book in bookData(section)"
                        :key="book.id"
                        :book="book"
                        compact-mobile
                    />
                </div>
            </section>

            <section
                v-else-if="section.type === 'categories' && directoryData(section).length"
                class="ui-page-shell py-3 sm:py-8 lg:py-9"
            >
                <PublicSectionHeader
                    :eyebrow="configString(section, 'eyebrow', 'Jelajahi topik')"
                    :title="configString(section, 'title', 'Kategori')"
                    :href="configBoolean(section, 'show_view_all') ? '/categories' : null"
                    link-label="Semua kategori"
                />

                <div class="mt-4 grid gap-2.5 sm:mt-6 sm:grid-cols-2 sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
                    <Link
                        v-for="item in directoryData(section)"
                        :key="item.slug"
                        :href="'/category/' + item.slug"
                        class="group flex min-w-0 items-center gap-3 rounded-[var(--radius-lg)] border border-line bg-surface px-3.5 py-3 transition-[transform,border-color,background-color] duration-150 hover:-translate-y-0.5 hover:border-line-strong hover:bg-surface-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/25"
                    >
                        <span class="grid size-10 shrink-0 place-items-center rounded-[var(--radius-md)] bg-brand-soft text-brand transition-colors group-hover:bg-brand group-hover:text-brand-foreground">
                            <CategoryIcon :name="item.icon" size-class="size-4.5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-ink transition-colors group-hover:text-brand">{{ item.name }}</p>
                            <p v-if="item.parent" class="mt-0.5 truncate text-[10px] text-ink-faint">{{ item.parent.name }}</p>
                            <p class="mt-1 text-[11px] font-medium text-ink-soft">{{ item.count }} buku</p>
                        </div>

                        <ArrowRight class="size-4 shrink-0 text-ink-faint transition-transform group-hover:translate-x-0.5 group-hover:text-brand" />
                    </Link>
                </div>
            </section>

            <section
                v-else-if="section.type === 'collections' && directoryData(section).length"
                class="ui-page-shell py-5 sm:py-8 lg:py-9"
            >
                <PublicSectionHeader
                    :eyebrow="configString(section, 'eyebrow', 'Pilihan koleksi')"
                    :title="configString(section, 'title', 'Jelajahi koleksi')"
                    :href="configBoolean(section, 'show_view_all') ? '/collections' : null"
                    link-label="Semua koleksi"
                />

                <div class="mt-4 grid gap-2.5 sm:mt-6 sm:grid-cols-2 sm:gap-3 lg:grid-cols-4">
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
                        <p class="mt-3 text-[11px] font-medium text-ink-faint">{{ item.count }} buku</p>
                    </Link>
                </div>
            </section>

            <section
                v-else-if="section.type === 'statistics' && statisticsData(section).length"
                class="my-4 border-y border-line bg-surface/75 sm:my-8"
            >
                <div class="ui-page-shell py-5 sm:py-8">
                    <div class="mb-3 sm:mb-5">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-brand sm:text-xs">
                            {{ configString(section, 'eyebrow', 'Perpustakaan dalam angka') }}
                        </p>
                        <h2 class="mt-1 text-xl font-semibold tracking-tight text-ink sm:text-[28px]">
                            {{ configString(section, 'title', 'Statistik') }}
                        </h2>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-3">
                        <div
                            v-for="item in statisticsData(section)"
                            :key="item.key"
                            class="min-w-0 rounded-xl border border-line/80 bg-surface px-3 py-3 sm:px-4 sm:py-4"
                        >
                            <p class="text-[23px] font-semibold leading-none tabular-nums tracking-tight text-ink sm:text-3xl">{{ item.value.toLocaleString('id-ID') }}</p>
                            <p class="mt-2 text-xs leading-4 text-ink-soft">{{ item.key === 'ebooks' ? 'Buku publik' : item.label }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <section class="ui-page-shell pb-4 pt-3 sm:pb-8 sm:pt-4">
            <div class="flex flex-col gap-3 rounded-xl bg-brand-soft px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:py-5">
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
