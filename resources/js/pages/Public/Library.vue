<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, Filter, RotateCcw, Search } from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicPagination from '@/components/public/PublicPagination.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type {
    DirectoryItem,
    PublicBookCard as PublicBook,
    PublicPaginator,
} from '@/types/public-library';

interface Filters {
    q: string;
    category: string;
    author: string;
    publisher: string;
    collection: string;
    language: string;
    year: number | null;
    sort: string;
    per_page: number;
}

const props = defineProps<{
    books: PublicPaginator<PublicBook>;
    filters: Filters;
    filterOptions: {
        categories: DirectoryItem[];
        authors: DirectoryItem[];
        publishers: DirectoryItem[];
        collections: DirectoryItem[];
        languages: DirectoryItem[];
    };
}>();

const q = ref(props.filters.q);
const category = ref(props.filters.category);
const author = ref(props.filters.author);
const publisher = ref(props.filters.publisher);
const collection = ref(props.filters.collection);
const language = ref(props.filters.language);
const year = ref<number | null>(props.filters.year);
const sort = ref(props.filters.sort);
const perPage = ref(props.filters.per_page);
const filtersOpen = ref(false);

const activeFilterCount = computed(() => [
    category.value,
    author.value,
    publisher.value,
    collection.value,
    language.value,
    year.value,
].filter((value) => value !== '' && value !== null).length);

function applyFilters() {
    const params: Record<string, string | number> = {};

    if (q.value.trim()) params.q = q.value.trim();
    if (category.value) params.category = category.value;
    if (author.value) params.author = author.value;
    if (publisher.value) params.publisher = publisher.value;
    if (collection.value) params.collection = collection.value;
    if (language.value) params.language = language.value;
    if (year.value) params.year = year.value;
    if (sort.value !== 'newest') params.sort = sort.value;
    if (perPage.value !== 12) params.per_page = perPage.value;

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
    language.value = '';
    year.value = null;
    sort.value = 'newest';
    perPage.value = 12;

    router.get('/library', {}, {
        preserveScroll: false,
        preserveState: false,
        replace: true,
    });
}
</script>

<template>
    <Head title="Katalog Ebook" />

    <PublicLayout>
        <section class="mx-auto px-5 py-10 sm:px-8 sm:py-14" style="max-width: var(--content-max-width)">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-primary">Perpustakaan</p>
                    <h1 class="mt-2 text-4xl font-semibold tracking-tight">Katalog Ebook</h1>
                    <p class="mt-4 text-base leading-7 text-muted-foreground">
                        Cari dan jelajahi ebook yang sudah dipublikasikan dan siap diakses.
                    </p>
                </div>

                <div class="text-sm text-muted-foreground">
                    {{ books.total }} ebook
                </div>
            </div>

            <form class="mt-8" role="search" @submit.prevent="applyFilters">
                <div class="flex items-center gap-3 rounded-2xl border border-border bg-surface px-4 py-3">
                    <Search class="size-5 shrink-0 text-muted-foreground" />
                    <input
                        v-model="q"
                        type="search"
                        class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        placeholder="Cari judul, penulis, kategori, penerbit, tag, atau ISBN..."
                        aria-label="Cari katalog ebook"
                    >
                    <button
                        type="submit"
                        class="inline-flex min-h-10 items-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground"
                    >
                        Cari
                    </button>
                </div>
            </form>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-border bg-surface px-3 text-sm font-medium lg:hidden"
                    @click="filtersOpen = !filtersOpen"
                >
                    <Filter class="size-4" />
                    Filter
                    <span v-if="activeFilterCount" class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
                        {{ activeFilterCount }}
                    </span>
                </button>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="sort"
                        class="min-h-10 rounded-xl border border-border bg-surface px-3 text-sm"
                        aria-label="Urutkan ebook"
                        @change="applyFilters"
                    >
                        <option value="newest">Terbaru</option>
                        <option value="title">Judul A–Z</option>
                        <option value="year_desc">Tahun terbaru</option>
                        <option value="year_asc">Tahun terlama</option>
                    </select>

                    <select
                        v-model="perPage"
                        class="min-h-10 rounded-xl border border-border bg-surface px-3 text-sm"
                        aria-label="Jumlah ebook per halaman"
                        @change="applyFilters"
                    >
                        <option :value="12">12 / halaman</option>
                        <option :value="24">24 / halaman</option>
                        <option :value="48">48 / halaman</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 grid gap-8 lg:grid-cols-[250px_minmax(0,1fr)]">
                <aside
                    class="rounded-2xl border border-border bg-surface p-5 lg:block"
                    :class="filtersOpen ? 'block' : 'hidden'"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold">Filter</p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                            @click="clearFilters"
                        >
                            <RotateCcw class="size-3.5" />
                            Reset
                        </button>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <label class="grid gap-1.5 text-sm">
                            <span class="text-xs font-medium text-muted-foreground">Kategori</span>
                            <select v-model="category" class="min-h-10 rounded-xl border border-border bg-background px-3" @change="applyFilters">
                                <option value="">Semua kategori</option>
                                <option v-for="item in filterOptions.categories" :key="item.slug" :value="item.slug">
                                    {{ item.name }} ({{ item.count }})
                                </option>
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-sm">
                            <span class="text-xs font-medium text-muted-foreground">Penulis</span>
                            <select v-model="author" class="min-h-10 rounded-xl border border-border bg-background px-3" @change="applyFilters">
                                <option value="">Semua penulis</option>
                                <option v-for="item in filterOptions.authors" :key="item.slug" :value="item.slug">
                                    {{ item.name }} ({{ item.count }})
                                </option>
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-sm">
                            <span class="text-xs font-medium text-muted-foreground">Penerbit</span>
                            <select v-model="publisher" class="min-h-10 rounded-xl border border-border bg-background px-3" @change="applyFilters">
                                <option value="">Semua penerbit</option>
                                <option v-for="item in filterOptions.publishers" :key="item.slug" :value="item.slug">
                                    {{ item.name }} ({{ item.count }})
                                </option>
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-sm">
                            <span class="text-xs font-medium text-muted-foreground">Koleksi</span>
                            <select v-model="collection" class="min-h-10 rounded-xl border border-border bg-background px-3" @change="applyFilters">
                                <option value="">Semua koleksi</option>
                                <option v-for="item in filterOptions.collections" :key="item.slug" :value="item.slug">
                                    {{ item.name }} ({{ item.count }})
                                </option>
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-sm">
                            <span class="text-xs font-medium text-muted-foreground">Bahasa</span>
                            <select v-model="language" class="min-h-10 rounded-xl border border-border bg-background px-3" @change="applyFilters">
                                <option value="">Semua bahasa</option>
                                <option v-for="item in filterOptions.languages" :key="item.slug" :value="item.slug">
                                    {{ item.name }} ({{ item.count }})
                                </option>
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-sm">
                            <span class="text-xs font-medium text-muted-foreground">Tahun terbit</span>
                            <input
                                v-model.number="year"
                                type="number"
                                min="1000"
                                max="9999"
                                class="min-h-10 rounded-xl border border-border bg-background px-3 outline-none"
                                placeholder="Contoh: 2026"
                                @keyup.enter="applyFilters"
                            >
                        </label>
                    </div>
                </aside>

                <div class="min-w-0">
                    <div v-if="books.data.length" class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 xl:grid-cols-4">
                        <PublicBookCard
                            v-for="book in books.data"
                            :key="book.id"
                            :book="book"
                        />
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-border bg-surface px-6 py-16 text-center">
                        <BookOpen class="mx-auto size-8 text-muted-foreground" />
                        <p class="mt-3 font-semibold">Ebook tidak ditemukan</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Ubah kata kunci atau filter, lalu coba kembali.
                        </p>
                        <button
                            type="button"
                            class="mt-5 text-sm font-semibold text-primary hover:underline"
                            @click="clearFilters"
                        >
                            Reset pencarian
                        </button>
                    </div>

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
    </PublicLayout>
</template>
