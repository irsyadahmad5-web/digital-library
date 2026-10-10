<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Building2,
    CalendarDays,
    ChevronRight,
    Download,
    FileText,
    Globe2,
    Hash,
    LibraryBig,
    UserRound,
} from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicSectionHeader from '@/components/public/PublicSectionHeader.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    readBookProgress,
    type ReaderProgressV1,
} from '@/composables/readerLocalState';
import type { SharedPageProps } from '@/types';
import type {
    PublicBookCard as PublicBook,
    PublicBookDetail,
} from '@/types/public-library';
import type { SeoPayload } from '@/types/seo';

const props = defineProps<{
    book: PublicBookDetail;
    relatedBooks: PublicBook[];
    seo: SeoPayload;
}>();

const page = usePage<SharedPageProps>();
const readingProgress = ref<ReaderProgressV1 | null>(null);

const downloadVisible = computed(() =>
    props.book.download_enabled
    && page.props.site.downloads.public_enabled !== false
    && page.props.site.downloads.show_download_button !== false,
);

const hasReadingProgress = computed(() =>
    Boolean(
        readingProgress.value
        && (
            readingProgress.value.page > 1
            || readingProgress.value.pageOffsetRatio >= 0.05
            || readingProgress.value.documentProgress >= 0.02
        )
    ),
);

const progressPercent = computed(() => {
    const progress = readingProgress.value;

    if (!progress || !hasReadingProgress.value) return 0;

    if (progress.documentProgress > 0) {
        return Math.min(100, Math.max(1, Math.round(progress.documentProgress * 100)));
    }

    if (progress.totalPages > 1) {
        return Math.min(100, Math.max(1, Math.round(((progress.page - 1) / (progress.totalPages - 1)) * 100)));
    }

    return 0;
});

const readButtonLabel = computed(() =>
    hasReadingProgress.value ? 'Lanjutkan membaca' : 'Baca sekarang',
);

const metadataCount = computed(() => [
    props.book.publisher,
    props.book.publication_year,
    props.book.page_count,
    props.book.language,
    props.book.collection,
    props.book.isbn,
    props.book.edition,
].filter(Boolean).length);

onMounted(() => {
    if (!props.book.read_enabled || !props.book.reader_revision) return;

    readingProgress.value = readBookProgress(
        props.book.slug,
        props.book.reader_revision,
    );
});
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="ui-page-shell py-8 sm:py-11">
            <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-1.5 text-xs text-ink-soft">
                <Link href="/library" class="shrink-0 hover:text-ink">Katalog</Link>
                <ChevronRight class="size-3.5 shrink-0 text-ink-faint" />
                <span class="truncate font-medium text-ink" aria-current="page">{{ book.title }}</span>
            </nav>

            <div class="mt-5 grid gap-7 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-10 xl:grid-cols-[270px_minmax(0,1fr)]">
                <aside class="mx-auto w-full max-w-[250px] lg:mx-0 lg:max-w-none">
                    <div class="lg:sticky lg:top-[94px]">
                        <div class="aspect-[3/4] overflow-hidden rounded-[var(--radius-xl)] border border-line bg-surface-subtle shadow-[var(--shadow-cover)]">
                            <img
                                v-if="book.cover_url"
                                :src="book.cover_url"
                                :alt="book.title"
                                class="size-full object-cover"
                                loading="eager"
                                decoding="async"
                                fetchpriority="high"
                            >
                            <div v-else class="flex size-full flex-col items-center justify-center gap-4 bg-brand-soft px-6 text-center text-brand">
                                <span class="grid size-12 place-items-center rounded-full bg-surface shadow-sm">
                                    <BookOpen class="size-6" />
                                </span>
                                <span class="line-clamp-4 text-sm font-semibold leading-6">{{ book.title }}</span>
                            </div>
                        </div>

                        <p v-if="book.publication_year || book.language" class="mt-3 text-center text-[11px] text-ink-faint lg:text-left">
                            <span v-if="book.publication_year">{{ book.publication_year }}</span>
                            <span v-if="book.publication_year && book.language" aria-hidden="true"> · </span>
                            <span v-if="book.language">{{ book.language.name }}</span>
                        </p>
                    </div>
                </aside>

                <div class="min-w-0">
                    <div v-if="book.categories.length || book.collection" class="flex flex-wrap items-center gap-2">
                        <Link
                            v-for="category in book.categories.slice(0, 3)"
                            :key="category.slug"
                            :href="'/category/' + category.slug"
                            class="rounded-full bg-brand-soft px-2.5 py-1 text-[11px] font-semibold text-brand transition-colors hover:bg-brand/10"
                        >
                            {{ category.name }}
                        </Link>
                        <Link
                            v-if="book.collection"
                            :href="'/collection/' + book.collection.slug"
                            class="text-[11px] font-semibold text-ink-soft hover:text-brand"
                        >
                            {{ book.collection.name }}
                        </Link>
                    </div>

                    <h1
                        class="mt-3 max-w-4xl text-balance text-3xl font-semibold leading-[1.12] tracking-[-0.03em] text-ink sm:text-4xl xl:text-[2.8rem]"
                        :class="book.categories.length || book.collection ? '' : 'mt-0'"
                    >
                        {{ book.title }}
                    </h1>

                    <p v-if="book.subtitle" class="mt-3 max-w-3xl text-base leading-7 text-ink-soft">
                        {{ book.subtitle }}
                    </p>

                    <div v-if="book.authors.length" class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        <UserRound class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                        <template v-for="(author, index) in book.authors" :key="author.slug">
                            <Link :href="'/author/' + author.slug" class="font-semibold text-ink hover:text-brand">
                                {{ author.name }}
                            </Link>
                            <span v-if="index < book.authors.length - 1" class="text-ink-faint">·</span>
                        </template>
                    </div>

                    <div
                        v-if="hasReadingProgress && readingProgress"
                        class="mt-6 max-w-2xl rounded-[var(--radius-lg)] bg-brand-soft p-4"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-brand">Lanjutkan dari sesi terakhir</p>
                                <p class="mt-1 text-xs text-ink-soft">
                                    Halaman {{ readingProgress.page }}
                                    <template v-if="readingProgress.totalPages"> dari {{ readingProgress.totalPages }}</template>
                                </p>
                            </div>
                            <span class="shrink-0 text-xs font-semibold tabular-nums text-brand">{{ progressPercent }}%</span>
                        </div>
                        <div class="mt-3 h-1 overflow-hidden rounded-full bg-brand/10" aria-hidden="true">
                            <div class="h-full rounded-full bg-brand" :style="{ width: progressPercent + '%' }" />
                        </div>
                    </div>

                    <div v-if="book.read_enabled || downloadVisible" class="mt-6 flex flex-wrap items-center gap-2.5">
                        <Button v-if="book.read_enabled" as-child size="large">
                            <Link :href="'/read/' + book.slug">
                                <BookOpen class="size-4" />
                                {{ readButtonLabel }}
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>

                        <Button v-if="downloadVisible" as-child size="large" :variant="book.read_enabled ? 'secondary' : 'primary'">
                            <a :href="'/book/' + book.slug + '/download'">
                                <Download class="size-4" />
                                Unduh PDF
                            </a>
                        </Button>
                    </div>

                    <div v-if="metadataCount" class="mt-7 max-w-3xl border-y border-line">
                        <dl class="grid sm:grid-cols-2">
                            <div v-if="book.publisher" class="flex gap-3 border-b border-line py-3.5 sm:pr-5">
                                <Building2 class="mt-0.5 size-4 shrink-0 text-ink-faint" />
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Penerbit</dt>
                                    <dd class="mt-1 text-sm font-medium text-ink">
                                        <Link :href="'/publisher/' + book.publisher.slug" class="hover:text-brand">{{ book.publisher.name }}</Link>
                                    </dd>
                                </div>
                            </div>

                            <div v-if="book.publication_year" class="flex gap-3 border-b border-line py-3.5 sm:pl-5">
                                <CalendarDays class="mt-0.5 size-4 shrink-0 text-ink-faint" />
                                <div>
                                    <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Tahun terbit</dt>
                                    <dd class="mt-1 text-sm font-medium text-ink">{{ book.publication_year }}</dd>
                                </div>
                            </div>

                            <div v-if="book.page_count" class="flex gap-3 border-b border-line py-3.5 sm:pr-5">
                                <FileText class="mt-0.5 size-4 shrink-0 text-ink-faint" />
                                <div>
                                    <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Halaman</dt>
                                    <dd class="mt-1 text-sm font-medium text-ink">{{ book.page_count }} halaman</dd>
                                </div>
                            </div>

                            <div v-if="book.language" class="flex gap-3 border-b border-line py-3.5 sm:pl-5">
                                <Globe2 class="mt-0.5 size-4 shrink-0 text-ink-faint" />
                                <div>
                                    <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Bahasa</dt>
                                    <dd class="mt-1 text-sm font-medium text-ink">{{ book.language.name }}</dd>
                                </div>
                            </div>

                            <div v-if="book.collection" class="flex gap-3 border-b border-line py-3.5 sm:pr-5">
                                <LibraryBig class="mt-0.5 size-4 shrink-0 text-ink-faint" />
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Koleksi</dt>
                                    <dd class="mt-1 text-sm font-medium text-ink">
                                        <Link :href="'/collection/' + book.collection.slug" class="hover:text-brand">{{ book.collection.name }}</Link>
                                    </dd>
                                </div>
                            </div>

                            <div v-if="book.isbn" class="flex gap-3 border-b border-line py-3.5 sm:pl-5">
                                <Hash class="mt-0.5 size-4 shrink-0 text-ink-faint" />
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">ISBN</dt>
                                    <dd class="mt-1 break-all text-sm font-medium text-ink">{{ book.isbn }}</dd>
                                </div>
                            </div>

                            <div v-if="book.edition" class="flex gap-3 py-3.5 sm:pr-5">
                                <FileText class="mt-0.5 size-4 shrink-0 text-ink-faint" />
                                <div>
                                    <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Edisi</dt>
                                    <dd class="mt-1 text-sm font-medium text-ink">{{ book.edition }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>

                    <div v-if="book.description" class="ui-reading-measure mt-8">
                        <h2 class="text-lg font-semibold tracking-tight text-ink">Tentang ebook ini</h2>
                        <p class="mt-3 whitespace-pre-line text-[15px] leading-7 text-ink-soft">
                            {{ book.description }}
                        </p>
                    </div>

                    <div v-if="book.tags.length" class="mt-7 border-t border-line pt-5">
                        <p class="text-xs font-semibold text-ink-soft">Topik terkait</p>
                        <div class="mt-2.5 flex flex-wrap gap-2">
                            <Link
                                v-for="tag in book.tags"
                                :key="tag.slug"
                                :href="'/library?tag=' + encodeURIComponent(tag.slug)"
                            >
                                <Badge tone="neutral">#{{ tag.name }}</Badge>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="relatedBooks.length" class="ui-page-shell pb-10 pt-4 sm:pb-14">
            <div class="border-t border-line pt-8">
                <PublicSectionHeader eyebrow="Rekomendasi" title="Ebook terkait" href="/library" />
                <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                    <PublicBookCard
                        v-for="item in relatedBooks"
                        :key="item.id"
                        :book="item"
                    />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
