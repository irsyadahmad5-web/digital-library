<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, FileText } from '@lucide/vue';
import type { PublicBookCard } from '@/types/public-library';

defineProps<{
    book: PublicBookCard;
}>();
</script>

<template>
    <article class="group min-w-0">
        <Link :href="`/book/${book.slug}`" class="block" prefetch="hover">
            <div class="relative aspect-[3/4] overflow-hidden rounded-2xl border border-border bg-muted">
                <img
                    v-if="book.cover_url"
                    :src="book.cover_url"
                    :alt="book.title"
                    class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                    loading="lazy"
                    decoding="async"
                    fetchpriority="low"
                >
                <div v-else class="flex size-full items-center justify-center">
                    <BookOpen class="size-9 text-muted-foreground/70" />
                </div>
            </div>

            <div class="pt-4">
                <p
                    v-if="book.categories.length"
                    class="line-clamp-1 text-xs font-medium text-primary"
                >
                    {{ book.categories[0].name }}
                </p>
                <h3 class="mt-1 line-clamp-2 font-semibold leading-6 tracking-tight transition-colors group-hover:text-primary">
                    {{ book.title }}
                </h3>
                <p class="mt-1 line-clamp-1 text-sm text-muted-foreground">
                    {{ book.authors.length ? book.authors.map((author) => author.name).join(', ') : 'Penulis belum dicantumkan' }}
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    <span v-if="book.publication_year">{{ book.publication_year }}</span>
                    <span v-if="book.page_count" class="inline-flex items-center gap-1">
                        <FileText class="size-3.5" />
                        {{ book.page_count }} hlm
                    </span>
                </div>
            </div>
        </Link>
    </article>
</template>
