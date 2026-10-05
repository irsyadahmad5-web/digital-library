<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen } from '@lucide/vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { DirectoryItem } from '@/types/public-library';
import type { SeoPayload } from '@/types/seo';

const props = defineProps<{
    title: string;
    type: 'category' | 'author' | 'publisher' | 'collection';
    items: DirectoryItem[];
    seo: SeoPayload;
}>();

const description = computed(() => ({
    category: 'Jelajahi ebook berdasarkan kategori dan subkategori.',
    author: 'Temukan karya berdasarkan penulis.',
    publisher: 'Jelajahi ebook berdasarkan penerbit.',
    collection: 'Temukan ebook yang dikelompokkan dalam koleksi.',
}[props.type]));

function href(slug: string) {
    return `/${props.type}/${slug}`;
}
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="mx-auto px-5 py-10 sm:px-8 sm:py-14" style="max-width: var(--content-max-width)">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-primary">Direktori</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight">{{ title }}</h1>
                <p class="mt-4 text-base leading-7 text-muted-foreground">{{ description }}</p>
            </div>

            <div v-if="items.length" class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="item in items"
                    :key="item.slug"
                    :href="href(item.slug)"
                    class="group rounded-2xl border border-border bg-surface p-5 transition-colors hover:bg-muted/50"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p v-if="item.parent" class="mb-1 text-xs font-medium text-primary">
                                {{ item.parent.name }}
                            </p>
                            <h2 class="font-semibold tracking-tight group-hover:text-primary">{{ item.name }}</h2>
                            <p v-if="item.description" class="mt-2 line-clamp-3 text-sm leading-6 text-muted-foreground">
                                {{ item.description }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                            {{ item.count }}
                        </span>
                    </div>

                    <div class="mt-5 flex items-center gap-2 text-sm font-medium text-primary">
                        Lihat ebook
                        <ArrowRight class="size-4 transition-transform group-hover:translate-x-0.5" />
                    </div>
                </Link>
            </div>

            <div v-else class="mt-10 rounded-2xl border border-dashed border-border bg-surface px-6 py-14 text-center">
                <BookOpen class="mx-auto size-8 text-muted-foreground" />
                <p class="mt-3 font-semibold">Belum ada data publik</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Direktori akan terisi setelah ebook dipublikasikan dan PDF selesai diproses.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
