<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen } from '@lucide/vue';
import type { PublicBookCard } from '@/types/public-library';

defineProps<{
    book: PublicBookCard;
}>();
</script>

<template>
    <article class="group min-w-0">
        <Link
            :href="'/book/' + book.slug"
            class="block rounded-[var(--radius-lg)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/25 focus-visible:ring-offset-4 focus-visible:ring-offset-canvas"
            prefetch="hover"
        >
            <div class="relative aspect-[3/4] overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface-subtle shadow-[var(--shadow-cover)] transition-[transform,box-shadow,border-color] duration-200 group-hover:-translate-y-1 group-hover:border-line-strong">
                <img
                    v-if="book.cover_url"
                    :src="book.cover_url"
                    :alt="book.title"
                    class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.015]"
                    loading="lazy"
                    decoding="async"
                    fetchpriority="low"
                >
                <div v-else class="flex size-full flex-col items-center justify-center gap-3 bg-brand-soft px-5 text-center">
                    <span class="grid size-10 place-items-center rounded-full bg-surface text-brand shadow-sm">
                        <BookOpen class="size-5" />
                    </span>
                    <span class="line-clamp-3 text-xs font-semibold leading-5 text-brand">{{ book.title }}</span>
                </div>
            </div>

            <div class="pt-3.5">
                <p
                    v-if="book.categories.length || book.collection"
                    class="line-clamp-1 text-[11px] font-semibold uppercase tracking-[0.06em] text-brand"
                >
                    {{ book.categories[0]?.name || book.collection?.name }}
                </p>

                <h3
                    class="line-clamp-2 text-[15px] font-semibold leading-[1.45] tracking-[-0.01em] text-ink transition-colors group-hover:text-brand"
                    :class="book.categories.length || book.collection ? 'mt-1' : ''"
                >
                    {{ book.title }}
                </h3>

                <p class="mt-1 line-clamp-1 text-xs leading-5 text-ink-soft">
                    {{ book.authors.length ? book.authors.map((author) => author.name).join(', ') : 'Penulis belum dicantumkan' }}
                </p>

                <p v-if="book.publication_year || book.language" class="mt-2 flex items-center gap-2 text-[11px] text-ink-faint">
                    <span v-if="book.publication_year">{{ book.publication_year }}</span>
                    <span v-if="book.publication_year && book.language" aria-hidden="true">·</span>
                    <span v-if="book.language" class="truncate">{{ book.language.name }}</span>
                </p>
            </div>
        </Link>
    </article>
</template>
