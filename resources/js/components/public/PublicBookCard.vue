<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Eye } from '@lucide/vue';
import type { PublicBookCard } from '@/types/public-library';

const props = withDefaults(defineProps<{
    book: PublicBookCard;
    compactMobile?: boolean;
}>(), { compactMobile: false });

function readerCount(value: number) {
    return new Intl.NumberFormat('id-ID', {
        notation: value >= 1000 ? 'compact' : 'standard',
        maximumFractionDigits: 1,
    }).format(Math.max(0, value));
}

function authorLabel() {
    if (!props.book.authors.length) return 'Penulis belum dicantumkan';

    return props.book.authors.map((author) => author.name).join(', ');
}
</script>

<template>
    <article class="group min-w-0">
        <Link
            :href="'/book/' + book.slug"
            class="rounded-[var(--radius-lg)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/35 focus-visible:ring-offset-4 focus-visible:ring-offset-canvas"
            :class="compactMobile ? 'flex w-full min-w-0 items-center gap-3 py-1 sm:block sm:py-0' : 'block'"
            :aria-label="'Buka buku ' + book.title"
            prefetch="hover"
        >
            <div
                class="relative overflow-hidden border border-line bg-surface-subtle shadow-[var(--shadow-cover)] transition-[transform,box-shadow,border-color] duration-200 group-hover:-translate-y-1 group-hover:border-line-strong group-focus-visible:-translate-y-1 group-focus-visible:border-line-strong"
                :class="compactMobile ? 'aspect-[3/4] w-[84px] shrink-0 rounded-lg sm:w-full sm:rounded-[var(--radius-lg)]' : 'aspect-[3/4] rounded-[var(--radius-lg)]'"
            >
                <img
                    v-if="book.cover_url"
                    :src="book.cover_url"
                    :alt="book.title"
                    class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.025] group-focus-visible:scale-[1.025]"
                    loading="lazy"
                    decoding="async"
                    fetchpriority="low"
                >

                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-3 bg-brand-soft px-5 text-center"
                >
                    <span class="grid size-11 place-items-center rounded-full bg-surface text-brand shadow-sm">
                        <BookOpen class="size-5" />
                    </span>
                    <span class="line-clamp-3 text-xs font-semibold leading-5 text-brand">{{ book.title }}</span>
                </div>

                <div
                    class="absolute inset-x-0 top-0 items-start justify-between gap-1 p-1 sm:gap-2 sm:p-2.5"
                    :class="compactMobile ? 'hidden sm:flex' : 'flex'"
                >
                    <span
                        v-if="book.categories.length"
                        class="max-w-[72%] truncate rounded-full border border-white/15 bg-black/62 px-2.5 py-1 text-[10px] font-semibold text-white shadow-sm backdrop-blur-sm"
                        :class="compactMobile ? 'max-w-[52%] px-1 py-0.5 text-[8px] sm:max-w-[72%] sm:px-2.5 sm:py-1 sm:text-[10px]' : ''"
                        :title="book.categories[0].name"
                    >
                        {{ book.categories[0].name }}
                    </span>
                    <span v-else />

                    <span
                        class="inline-flex min-h-6 shrink-0 items-center gap-1 rounded-full border border-white/15 bg-black/62 px-2 py-1 text-[10px] font-semibold tabular-nums text-white shadow-sm backdrop-blur-sm"
                        :class="compactMobile ? 'min-h-4 gap-0.5 px-1 py-0.5 text-[8px] sm:min-h-6 sm:gap-1 sm:px-2 sm:py-1 sm:text-[10px]' : ''"
                        :title="book.reader_count.toLocaleString('id-ID') + ' kali dibaca'"
                    >
                        <Eye class="size-3.5" :class="compactMobile ? 'size-2.5 sm:size-3.5' : ''" />
                        {{ readerCount(book.reader_count) }}
                    </span>
                </div>

                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/92 via-black/62 to-transparent px-3 pb-3 pt-16 text-white transition-[opacity,transform] duration-200 md:translate-y-2 md:opacity-0 md:group-hover:translate-y-0 md:group-hover:opacity-100 md:group-focus-visible:translate-y-0 md:group-focus-visible:opacity-100"
                    :class="compactMobile ? 'hidden sm:block' : ''"
                >
                    <h3 class="line-clamp-2 text-sm font-semibold leading-5 tracking-[-0.01em]">
                        {{ book.title }}
                    </h3>
                    <p class="mt-1 line-clamp-1 text-[10px] leading-4 text-white/75">
                        {{ authorLabel() }}
                    </p>
                </div>
            </div>
            <div v-if="compactMobile" class="flex min-w-0 flex-1 flex-col justify-center gap-1.5 sm:hidden">
                <h3 class="line-clamp-2 text-[14px] font-semibold leading-5 text-ink">{{ book.title }}</h3>
                <p class="line-clamp-2 text-[11px] leading-4 text-ink-soft">{{ authorLabel() }}</p>
                <div class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-[11px] leading-4">
                    <span v-if="book.categories.length" class="max-w-full truncate rounded-md bg-brand-soft px-2 py-0.5 font-medium text-brand" :title="book.categories[0].name">{{ book.categories[0].name }}</span>
                    <span class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-ink-faint" :aria-label="book.reader_count.toLocaleString('id-ID') + ' kali dibaca'">
                        <Eye class="size-3.5" aria-hidden="true" />
                        {{ readerCount(book.reader_count) }} dibaca
                    </span>
                </div>
            </div>
        </Link>
    </article>
</template>
