<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Building2,
    CalendarDays,
    Download,
    FileText,
    Globe2,
    Hash,
    LibraryBig,
    UserRound,
} from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import {
    readBookProgress,
    type ReaderProgressV1,
} from '@/composables/readerLocalState';
import type { SharedPageProps } from '@/types';
import type {
    PublicBookCard as PublicBook,
    PublicBookDetail,
} from '@/types/public-library';

const props = defineProps<{
    book: PublicBookDetail;
    relatedBooks: PublicBook[];
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

const readButtonLabel = computed(() =>
    hasReadingProgress.value
        ? `Lanjutkan · halaman ${readingProgress.value?.page ?? 1}`
        : 'Baca Ebook',
);

onMounted(() => {
    if (!props.book.read_enabled || !props.book.reader_revision) return;

    readingProgress.value = readBookProgress(
        props.book.slug,
        props.book.reader_revision,
    );
});
</script>

<template>
    <Head>
        <title>{{ book.title }}</title>
        <meta
            v-if="book.description"
            name="description"
            :content="book.description.slice(0, 300)"
        >
    </Head>

    <PublicLayout>
        <section class="mx-auto px-5 py-10 sm:px-8 sm:py-14" style="max-width: var(--content-max-width)">
            <Link href="/library" class="text-sm font-medium text-muted-foreground hover:text-foreground">
                ← Kembali ke katalog
            </Link>

            <div class="mt-8 grid gap-8 lg:grid-cols-[260px_minmax(0,1fr)] xl:grid-cols-[300px_minmax(0,1fr)]">
                <div>
                    <div class="aspect-[3/4] overflow-hidden rounded-3xl border border-border bg-muted">
                        <img
                            v-if="book.cover_url"
                            :src="book.cover_url"
                            :alt="book.title"
                            class="size-full object-cover"
                        >
                        <div v-else class="flex size-full items-center justify-center">
                            <BookOpen class="size-12 text-muted-foreground/70" />
                        </div>
                    </div>
                </div>

                <div class="min-w-0">
                    <div v-if="book.categories.length" class="flex flex-wrap gap-2">
                        <Link
                            v-for="category in book.categories"
                            :key="category.slug"
                            :href="`/category/${category.slug}`"
                            class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            {{ category.name }}
                        </Link>
                    </div>

                    <h1 class="mt-4 text-balance text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                        {{ book.title }}
                    </h1>
                    <p v-if="book.subtitle" class="mt-3 text-lg leading-7 text-muted-foreground">
                        {{ book.subtitle }}
                    </p>

                    <div v-if="book.authors.length" class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        <UserRound class="size-4 text-muted-foreground" />
                        <template v-for="(author, index) in book.authors" :key="author.slug">
                            <Link :href="`/author/${author.slug}`" class="font-medium hover:text-primary hover:underline">
                                {{ author.name }}
                            </Link>
                            <span v-if="index < book.authors.length - 1" class="text-muted-foreground">•</span>
                        </template>
                    </div>

                    <div
                        v-if="book.read_enabled || downloadVisible"
                        class="mt-7 flex flex-wrap items-center gap-3"
                    >
                        <Link
                            v-if="book.read_enabled"
                            :href="`/read/${book.slug}`"
                            class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground hover:bg-primary-hover"
                        >
                            <BookOpen class="size-4" />
                            {{ readButtonLabel }}
                            <ArrowRight class="size-4" />
                        </Link>

                        <a
                            v-if="downloadVisible"
                            :href="`/book/${book.slug}/download`"
                            class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-border bg-surface px-5 text-sm font-semibold text-foreground hover:bg-muted"
                        >
                            <Download class="size-4" />
                            Unduh PDF
                        </a>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-if="book.publisher" class="rounded-2xl border border-border bg-surface p-4">
                            <Building2 class="size-4 text-primary" />
                            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Penerbit</p>
                            <Link :href="`/publisher/${book.publisher.slug}`" class="mt-1 block text-sm font-semibold hover:text-primary">
                                {{ book.publisher.name }}
                            </Link>
                        </div>

                        <div v-if="book.publication_year" class="rounded-2xl border border-border bg-surface p-4">
                            <CalendarDays class="size-4 text-primary" />
                            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Tahun</p>
                            <p class="mt-1 text-sm font-semibold">{{ book.publication_year }}</p>
                        </div>

                        <div v-if="book.page_count" class="rounded-2xl border border-border bg-surface p-4">
                            <FileText class="size-4 text-primary" />
                            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Halaman</p>
                            <p class="mt-1 text-sm font-semibold">{{ book.page_count }} halaman</p>
                        </div>

                        <div v-if="book.language" class="rounded-2xl border border-border bg-surface p-4">
                            <Globe2 class="size-4 text-primary" />
                            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Bahasa</p>
                            <p class="mt-1 text-sm font-semibold">{{ book.language.name }}</p>
                        </div>

                        <div v-if="book.collection" class="rounded-2xl border border-border bg-surface p-4">
                            <LibraryBig class="size-4 text-primary" />
                            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">Koleksi</p>
                            <Link :href="`/collection/${book.collection.slug}`" class="mt-1 block text-sm font-semibold hover:text-primary">
                                {{ book.collection.name }}
                            </Link>
                        </div>

                        <div v-if="book.isbn" class="rounded-2xl border border-border bg-surface p-4">
                            <Hash class="size-4 text-primary" />
                            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">ISBN</p>
                            <p class="mt-1 break-all text-sm font-semibold">{{ book.isbn }}</p>
                        </div>
                    </div>

                    <div v-if="book.description" class="mt-9 border-t border-border pt-8">
                        <h2 class="text-xl font-semibold tracking-tight">Tentang ebook ini</h2>
                        <p class="mt-4 whitespace-pre-line text-base leading-8 text-muted-foreground">
                            {{ book.description }}
                        </p>
                    </div>

                    <div v-if="book.edition || book.tags.length" class="mt-8 border-t border-border pt-7">
                        <p v-if="book.edition" class="text-sm text-muted-foreground">
                            Edisi: <span class="font-medium text-foreground">{{ book.edition }}</span>
                        </p>
                        <div v-if="book.tags.length" class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="tag in book.tags"
                                :key="tag.slug"
                                class="rounded-full border border-border bg-surface px-3 py-1 text-xs text-muted-foreground"
                            >
                                #{{ tag.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="relatedBooks.length"
            class="mx-auto px-5 pb-8 pt-4 sm:px-8 sm:pb-14"
            style="max-width: var(--content-max-width)"
        >
            <div class="border-t border-border pt-10">
                <p class="text-sm font-medium text-primary">Rekomendasi</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">Ebook terkait</h2>

                <div class="mt-7 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
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
