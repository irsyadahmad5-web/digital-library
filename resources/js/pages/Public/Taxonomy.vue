<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen } from '@lucide/vue';
import PublicBookCard from '@/components/public/PublicBookCard.vue';
import PublicPagination from '@/components/public/PublicPagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
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
        `/${props.type}/${props.taxonomy.slug}`,
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
        <section class="mx-auto px-5 py-10 sm:px-8 sm:py-14" style="max-width: var(--content-max-width)">
            <Link
                :href="directoryUrls[type]"
                class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ labels[type] }}
            </Link>

            <div class="mt-8 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-primary">{{ labels[type] }}</p>
                    <h1 class="mt-2 text-4xl font-semibold tracking-tight">{{ taxonomy.name }}</h1>
                    <p v-if="taxonomy.parent" class="mt-2 text-sm text-muted-foreground">
                        Subkategori dari
                        <Link :href="`/category/${taxonomy.parent.slug}`" class="font-medium text-foreground hover:underline">
                            {{ taxonomy.parent.name }}
                        </Link>
                    </p>
                    <p v-if="taxonomy.description" class="mt-4 whitespace-pre-line text-base leading-7 text-muted-foreground">
                        {{ taxonomy.description }}
                    </p>
                </div>

                <p class="text-sm text-muted-foreground">{{ books.total }} ebook</p>
            </div>

            <div class="mt-8 flex flex-wrap justify-end gap-2">
                <select v-model="sort" class="min-h-10 rounded-xl border border-border bg-surface px-3 text-sm" @change="apply">
                    <option value="newest">Terbaru</option>
                    <option value="title">Judul A–Z</option>
                    <option value="year_desc">Tahun terbaru</option>
                    <option value="year_asc">Tahun terlama</option>
                </select>
                <select v-model="perPage" class="min-h-10 rounded-xl border border-border bg-surface px-3 text-sm" @change="apply">
                    <option :value="12">12 / halaman</option>
                    <option :value="24">24 / halaman</option>
                    <option :value="48">48 / halaman</option>
                </select>
            </div>

            <div v-if="books.data.length" class="mt-7 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <PublicBookCard
                    v-for="book in books.data"
                    :key="book.id"
                    :book="book"
                />
            </div>

            <div v-else class="mt-8 rounded-2xl border border-dashed border-border bg-surface px-6 py-14 text-center">
                <BookOpen class="mx-auto size-8 text-muted-foreground" />
                <p class="mt-3 font-semibold">Belum ada ebook publik</p>
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
        </section>
    </PublicLayout>
</template>
