<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { BookOpen, ChevronRight } from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicPagination from '@/components/public/PublicPagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { EmptyState } from '@/components/ui/empty-state';
import { Select } from '@/components/ui/select';
import type {
    PublicBookCard as PublicBook,
    PublicPaginator,
    TaxonomyInfo,
} from '@/types/public-library';
import type { SeoPayload } from '@/types/seo';

const props = defineProps<{
    type: 'category' | 'author' | 'publisher' | 'collection';
    taxonomy: TaxonomyInfo;
    books: PublicPaginator<PublicBook>;
    seo: SeoPayload;
    filters: {
        sort: string;
        per_page: number;
    };
}>();

const sort = ref(props.filters.sort);
const perPage = ref(props.filters.per_page);

const labels = {
    category: 'Kategori',
    author: 'Penulis',
    publisher: 'Penerbit',
    collection: 'Koleksi',
};

const directoryUrls = {
    category: '/categories',
    author: '/authors',
    publisher: '/publishers',
    collection: '/collections',
};

function apply() {
    const params: Record<string, string | number> = {};

    if (sort.value !== 'newest') params.sort = sort.value;
    if (perPage.value !== 12) params.per_page = perPage.value;

    router.get(
        '/' + props.type + '/' + props.taxonomy.slug,
        params,
        {
            preserveScroll: false,
            preserveState: false,
            replace: true,
        },
    );
}
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="ui-page-shell py-9 sm:py-12">
            <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-xs text-ink-soft">
                <Link href="/library" class="hover:text-ink">Katalog</Link>
                <ChevronRight class="size-3.5 text-ink-faint" />
                <Link :href="directoryUrls[type]" class="hover:text-ink">{{ labels[type] }}</Link>
                <ChevronRight class="size-3.5 text-ink-faint" />
                <span class="max-w-52 truncate font-medium text-ink" aria-current="page">{{ taxonomy.name }}</span>
            </nav>

            <div class="mt-5 flex flex-col gap-4 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-brand">{{ labels[type] }}</p>
                    <h1 class="mt-1.5 text-balance text-3xl font-semibold tracking-[-0.025em] text-ink sm:text-4xl">{{ taxonomy.name }}</h1>
                    <p v-if="taxonomy.parent" class="mt-2 text-xs text-ink-soft">
                        Subkategori dari
                        <Link :href="'/category/' + taxonomy.parent.slug" class="font-semibold text-ink hover:text-brand">
                            {{ taxonomy.parent.name }}
                        </Link>
                    </p>
                    <p v-if="taxonomy.description" class="ui-reading-measure mt-3 whitespace-pre-line text-sm leading-6 text-ink-soft">
                        {{ taxonomy.description }}
                    </p>
                </div>

                <p class="text-xs font-medium tabular-nums text-ink-soft">{{ books.total }} ebook</p>
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-ink-soft">
                    {{ books.total ? books.total + ' ebook dalam ' + labels[type].toLowerCase() + ' ini' : 'Belum ada ebook publik' }}
                </p>

                <div class="flex items-center gap-2">
                    <Select v-model="sort" class="w-auto min-w-36" aria-label="Urutkan ebook" @change="apply">
                        <option value="popular">Paling populer</option>
                        <option value="newest">Terbaru</option>
                        <option value="title">Judul A–Z</option>
                        <option value="year_desc">Tahun terbaru</option>
                        <option value="year_asc">Tahun terlama</option>
                    </Select>
                    <Select v-model="perPage" class="hidden w-auto min-w-32 sm:block" aria-label="Jumlah ebook per halaman" @change="apply">
                        <option :value="12">12 / halaman</option>
                        <option :value="24">24 / halaman</option>
                        <option :value="48">48 / halaman</option>
                    </Select>
                </div>
            </div>

            <div v-if="books.data.length" class="mt-6 grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <PublicBookCard
                    v-for="book in books.data"
                    :key="book.id"
                    :book="book"
                />
            </div>

            <EmptyState
                v-else
                class="mt-8"
                title="Belum ada ebook publik"
                description="Koleksi akan muncul di halaman ini setelah ebook terkait dipublikasikan dan siap dibaca."
            >
                <template #icon><BookOpen class="size-5" /></template>
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
        </section>
    </PublicLayout>
</template>
