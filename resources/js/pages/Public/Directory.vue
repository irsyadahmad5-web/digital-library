<script setup lang="ts">
import { computed } from 'vue';
import { BookOpen } from '@lucide/vue';
import PublicDirectoryCard from '@/components/public/PublicDirectoryCard.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { EmptyState } from '@/components/ui/empty-state';
import type { DirectoryItem } from '@/types/public-library';
import type { SeoPayload } from '@/types/seo';

const props = defineProps<{
    title: string;
    type: 'category' | 'author' | 'publisher' | 'collection';
    items: DirectoryItem[];
    seo: SeoPayload;
}>();

const description = computed(() => ({
    category: 'Jelajahi koleksi berdasarkan topik dan subkategori.',
    author: 'Temukan karya berdasarkan penulis yang tersedia.',
    publisher: 'Jelajahi koleksi berdasarkan penerbit.',
    collection: 'Temukan ebook melalui koleksi yang telah dikurasi.',
}[props.type]));

const eyebrow = computed(() => ({
    category: 'Topik',
    author: 'Kontributor',
    publisher: 'Referensi',
    collection: 'Kurasi',
}[props.type]));

function href(slug: string) {
    return '/' + props.type + '/' + slug;
}
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="ui-page-shell py-9 sm:py-12">
            <div class="flex flex-col gap-4 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-brand">{{ eyebrow }}</p>
                    <h1 class="mt-1.5 text-3xl font-semibold tracking-[-0.025em] text-ink sm:text-4xl">{{ title }}</h1>
                    <p class="mt-2 text-sm leading-6 text-ink-soft">{{ description }}</p>
                </div>
                <p v-if="items.length" class="text-xs font-medium tabular-nums text-ink-soft">{{ items.length }} entri</p>
            </div>

            <div v-if="items.length" class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <PublicDirectoryCard
                    v-for="item in items"
                    :key="item.slug"
                    :item="item"
                    :href="href(item.slug)"
                />
            </div>

            <EmptyState
                v-else
                class="mt-8"
                title="Belum ada data publik"
                description="Direktori akan terisi setelah ebook dipublikasikan dan PDF selesai diproses."
            >
                <template #icon><BookOpen class="size-5" /></template>
            </EmptyState>
        </section>
    </PublicLayout>
</template>
