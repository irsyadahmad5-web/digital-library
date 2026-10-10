<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { BookOpen, Filter, RotateCcw, Search, SlidersHorizontal } from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicCatalogFilters from '@/components/public/PublicCatalogFilters.vue';
import PublicPagination from '@/components/public/PublicPagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { EmptyState } from '@/components/ui/empty-state';
import { Select } from '@/components/ui/select';
import { SheetShell } from '@/components/ui/sheet';
import type {
    DirectoryItem,
    PublicBookCard as PublicBook,
    PublicPaginator,
} from '@/types/public-library';
import type { SeoPayload } from '@/types/seo';

interface Filters {
    q: string;
    category: string;
    author: string;
    publisher: string;
    collection: string;
    tag: string;
    language: string;
    year: number | null;
    sort: string;
    per_page: number;
}

type FilterKey = 'category' | 'author' | 'publisher' | 'collection' | 'tag' | 'language' | 'year';

const props = defineProps<{
    books: PublicPaginator<PublicBook>;
    filters: Filters;
    seo: SeoPayload;
    filterOptions: {
        categories: DirectoryItem[];
        authors: DirectoryItem[];
        publishers: DirectoryItem[];
        collections: DirectoryItem[];
        tags: DirectoryItem[];
        languages: DirectoryItem[];
    };
}>();

const q = ref(props.filters.q);
const category = ref(props.filters.category);
const author = ref(props.filters.author);
const publisher = ref(props.filters.publisher);
const collection = ref(props.filters.collection);
const tag = ref(props.filters.tag);
const language = ref(props.filters.language);
const year = ref<number | null>(props.filters.year);
const sort = ref(props.filters.sort);
const perPage = ref(props.filters.per_page);
const filtersOpen = ref(false);

function optionName(items: DirectoryItem[], slug: string) {
    return items.find((item) => item.slug === slug)?.name || slug;
}

const activeFilters = computed(() => {
    const items: { key: FilterKey; label: string }[] = [];

    if (category.value) items.push({ key: 'category', label: 'Kategori: ' + optionName(props.filterOptions.categories, category.value) });
    if (author.value) items.push({ key: 'author', label: 'Penulis: ' + optionName(props.filterOptions.authors, author.value) });
    if (publisher.value) items.push({ key: 'publisher', label: 'Penerbit: ' + optionName(props.filterOptions.publishers, publisher.value) });
    if (collection.value) items.push({ key: 'collection', label: 'Koleksi: ' + optionName(props.filterOptions.collections, collection.value) });
    if (tag.value) items.push({ key: 'tag', label: 'Tag: ' + optionName(props.filterOptions.tags, tag.value) });
    if (language.value) items.push({ key: 'language', label: 'Bahasa: ' + optionName(props.filterOptions.languages, language.value) });
    if (year.value) items.push({ key: 'year', label: 'Tahun: ' + year.value });

    return items;
});

const activeFilterCount = computed(() => activeFilters.value.length);
const resultSummary = computed(() => {
    if (props.filters.q) return props.books.total + ' hasil untuk “' + props.filters.q + '”';

    return props.books.total + ' ebook tersedia';
});

function applyFilters(closeSheet = false) {
    const params: Record<string, string | number> = {};

    if (q.value.trim()) params.q = q.value.trim();
    if (category.value) params.category = category.value;
    if (author.value) params.author = author.value;
    if (publisher.value) params.publisher = publisher.value;
    if (collection.value) params.collection = collection.value;
    if (tag.value) params.tag = tag.value;
    if (language.value) params.language = language.value;
    if (year.value) params.year = year.value;

    const normalizedQuery = q.value.trim();
    const queryChanged = normalizedQuery !== props.filters.q.trim();
    let effectiveSort = sort.value;

    if (queryChanged) {
        effectiveSort = normalizedQuery ? 'relevance' : 'newest';
        sort.value = effectiveSort;
    }

    const defaultSort = normalizedQuery ? 'relevance' : 'newest';

    if (effectiveSort !== defaultSort) params.sort = effectiveSort;
    if (perPage.value !== 12) params.per_page = perPage.value;

    if (closeSheet) filtersOpen.value = false;

    router.get('/library', params, {
        preserveScroll: false,
        preserveState: false,
        replace: true,
    });
}

function clearFilters() {
    q.value = '';
    category.value = '';
    author.value = '';
    publisher.value = '';
    collection.value = '';
    tag.value = '';
    language.value = '';
    year.value = null;
    sort.value = 'newest';
    perPage.value = 12;
    filtersOpen.value = false;

    router.get('/library', {}, {
        preserveScroll: false,
        preserveState: false,
        replace: true,
    });
}

function removeFilter(key: FilterKey) {
    if (key === 'category') category.value = '';
    if (key === 'author') author.value = '';
    if (key === 'publisher') publisher.value = '';
    if (key === 'collection') collection.value = '';
    if (key === 'tag') tag.value = '';
    if (key === 'language') language.value = '';
    if (key === 'year') year.value = null;

    applyFilters();
}
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="ui-page-shell py-9 sm:py-12">
            <div class="flex flex-col gap-4 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-brand">Perpustakaan</p>
                    <h1 class="mt-1.5 text-3xl font-semibold tracking-[-0.025em] text-ink sm:text-4xl">Katalog Ebook</h1>
                    <p class="mt-2 text-sm leading-6 text-ink-soft">
                        Temukan bacaan melalui judul, penulis, topik, penerbit, koleksi, bahasa, atau ISBN.
                    </p>
                </div>
                <p class="text-xs font-medium tabular-nums text-ink-soft">{{ books.total.toLocaleString('id-ID') }} ebook</p>
            </div>

            <form class="mt-6" role="search" @submit.prevent="applyFilters()">
                <div class="flex min-h-12 items-center rounded-[var(--radius-lg)] border border-line bg-surface px-3.5 transition-[border-color,box-shadow] focus-within:border-brand/45 focus-within:shadow-[0_0_0_3px_color-mix(in_srgb,var(--brand)_12%,transparent)]">
                    <Search class="size-[18px] shrink-0 text-ink-faint" aria-hidden="true" />
                    <input
                        v-model="q"
                        type="search"
                        class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none placeholder:text-ink-faint"
                        placeholder="Cari judul, penulis, topik, kategori, atau ISBN…"
                        aria-label="Cari katalog ebook"
                    >
                    <Button type="submit" size="small" class="shrink-0">Cari</Button>
                </div>
            </form>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center gap-2 rounded-[var(--radius-md)] border border-line bg-surface px-3 text-xs font-semibold text-ink lg:hidden"
                    :aria-expanded="filtersOpen"
                    @click="filtersOpen = true"
                >
                    <Filter class="size-4" />
                    Filter
                    <span v-if="activeFilterCount" class="rounded-full bg-brand px-1.5 py-0.5 text-[10px] text-brand-foreground">
                        {{ activeFilterCount }}
                    </span>
                </button>

                <template v-for="item in activeFilters" :key="item.key">
                    <Chip removable active @remove="removeFilter(item.key)">{{ item.label }}</Chip>
                </template>

                <button
                    v-if="activeFilterCount || filters.q"
                    type="button"
                    class="inline-flex min-h-8 items-center gap-1.5 rounded-full px-2.5 text-xs font-semibold text-ink-soft hover:bg-surface-subtle hover:text-ink"
                    @click="clearFilters"
                >
                    <RotateCcw class="size-3.5" />
                    Reset semua
                </button>
            </div>

            <div class="mt-5 grid gap-7 lg:grid-cols-[220px_minmax(0,1fr)] xl:grid-cols-[236px_minmax(0,1fr)]">
                <aside class="hidden lg:block">
                    <div class="sticky top-[92px]">
                        <div class="mb-4 flex items-center gap-2">
                            <SlidersHorizontal class="size-4 text-ink-faint" />
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-ink-soft">Filter katalog</p>
                        </div>

                        <PublicCatalogFilters
                            v-model:category="category"
                            v-model:author="author"
                            v-model:publisher="publisher"
                            v-model:collection="collection"
                            v-model:tag="tag"
                            v-model:language="language"
                            v-model:year="year"
                            :filter-options="filterOptions"
                            auto-apply
                            @apply="applyFilters()"
                            @reset="clearFilters"
                        />
                    </div>
                </aside>

                <div class="min-w-0">
                    <div class="flex flex-col gap-3 border-b border-line pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs font-medium text-ink-soft">{{ resultSummary }}</p>

                        <div class="flex items-center gap-2">
                            <Select
                                v-model="sort"
                                class="w-auto min-w-36"
                                aria-label="Urutkan ebook"
                                @change="applyFilters()"
                            >
                                <option v-if="q.trim()" value="relevance">Paling relevan</option>
                                <option value="popular">Paling populer</option>
                                <option value="newest">Terbaru</option>
                                <option value="title">Judul A–Z</option>
                                <option value="year_desc">Tahun terbaru</option>
                                <option value="year_asc">Tahun terlama</option>
                            </Select>

                            <Select
                                v-model="perPage"
                                class="hidden w-auto min-w-32 sm:block"
                                aria-label="Jumlah ebook per halaman"
                                @change="applyFilters()"
                            >
                                <option :value="12">12 / halaman</option>
                                <option :value="24">24 / halaman</option>
                                <option :value="48">48 / halaman</option>
                            </Select>
                        </div>
                    </div>

                    <div v-if="books.data.length" class="mt-6 grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3 xl:grid-cols-4">
                        <PublicBookCard
                            v-for="book in books.data"
                            :key="book.id"
                            :book="book"
                        />
                    </div>

                    <EmptyState
                        v-else
                        class="mt-8"
                        title="Ebook tidak ditemukan"
                        description="Coba gunakan kata kunci yang lebih singkat, hapus beberapa filter, atau kembali ke seluruh katalog."
                    >
                        <template #icon><BookOpen class="size-5" /></template>
                        <template #actions>
                            <Button variant="secondary" @click="clearFilters">Reset pencarian</Button>
                        </template>
                    </EmptyState>

                    <PublicPagination
                        :current-page="books.current_page"
                        :last-page="books.last_page"
                        :total="books.total"
                        :from="books.from"
                        :to="books.to"
                        :prev-url="books.prev_page_url"
                        :next-url="books.next_page_url"
                    />
                </div>
            </div>
        </section>

        <SheetShell
            :open="filtersOpen"
            side="bottom"
            title="Filter katalog"
            :description="activeFilterCount + ' filter aktif · ' + books.total + ' ebook saat ini'"
            @update:open="filtersOpen = $event"
        >
            <PublicCatalogFilters
                v-model:category="category"
                v-model:author="author"
                v-model:publisher="publisher"
                v-model:collection="collection"
                v-model:tag="tag"
                v-model:language="language"
                v-model:year="year"
                :filter-options="filterOptions"
                @apply="applyFilters(true)"
                @reset="clearFilters"
            />

            <div class="sticky bottom-0 -mx-5 mt-5 border-t border-line bg-surface px-5 pb-[max(.25rem,env(safe-area-inset-bottom))] pt-4">
                <Button class="w-full" @click="applyFilters(true)">Terapkan filter</Button>
            </div>
        </SheetShell>
    </PublicLayout>
</template>
