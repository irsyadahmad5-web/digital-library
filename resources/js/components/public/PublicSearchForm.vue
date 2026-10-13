<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowRight, Search } from '@lucide/vue';

const props = withDefaults(defineProps<{
    initialQuery?: string;
    placeholder?: string;
    compact?: boolean;
    showSubmit?: boolean;
    autoFocus?: boolean;
}>(), {
    initialQuery: '',
    placeholder: 'Cari judul, penulis, topik, kategori, atau ISBN…',
    compact: false,
    showSubmit: true,
    autoFocus: false,
});

const query = ref(props.initialQuery);

watch(() => props.initialQuery, (value) => {
    if (value !== query.value) query.value = value;
});

function submit() {
    const value = query.value.trim();

    router.get(
        '/library',
        value ? { q: value } : {},
        {
            preserveScroll: false,
            preserveState: false,
        },
    );
}
</script>

<template>
    <form
        class="group flex w-full min-w-0 max-w-full items-center gap-1 overflow-hidden rounded-xl border border-line bg-surface p-1 shadow-sm transition-[border-color,box-shadow] focus-within:border-brand/45 focus-within:shadow-[0_0_0_3px_color-mix(in_srgb,var(--brand)_12%,transparent)]"
        :class="compact ? 'min-h-11' : 'min-h-12'"
        role="search"
        @submit.prevent="submit"
    >
        <Search class="ml-2 size-[18px] shrink-0 text-ink-faint transition-colors group-focus-within:text-brand" aria-hidden="true" />
        <input
            v-model="query"
            type="search"
            enterkeyhint="search"
            class="min-w-0 w-0 flex-1 appearance-none bg-transparent px-1.5 text-[13px] text-ink outline-none placeholder:text-ink-faint sm:text-sm"
            :placeholder="placeholder"
            :autofocus="autoFocus"
            aria-label="Cari buku"
        >
        <button
            v-if="showSubmit"
            type="submit"
            class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-lg bg-brand px-3 text-xs font-semibold text-brand-foreground transition-colors hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 sm:px-4"
            aria-label="Cari buku"
        >
            Cari
            <ArrowRight v-if="!compact" class="size-3.5" aria-hidden="true" />
        </button>
    </form>
</template>
