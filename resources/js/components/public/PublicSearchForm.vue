<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowRight, Search } from '@lucide/vue';
import { Button } from '@/components/ui/button';

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
        class="group flex items-center border border-line bg-surface transition-[border-color,box-shadow] focus-within:border-brand/45 focus-within:shadow-[0_0_0_3px_color-mix(in_srgb,var(--brand)_12%,transparent)]"
        :class="compact ? 'min-h-10 rounded-[var(--radius-md)] px-3' : 'min-h-12 rounded-[var(--radius-lg)] px-4'"
        role="search"
        @submit.prevent="submit"
    >
        <Search
            class="shrink-0 text-ink-faint transition-colors group-focus-within:text-brand"
            :class="compact ? 'size-4' : 'size-[18px]'"
            aria-hidden="true"
        />
        <input
            v-model="query"
            type="search"
            class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none placeholder:text-ink-faint"
            :placeholder="placeholder"
            :autofocus="autoFocus"
            aria-label="Cari ebook"
        >
        <Button
            v-if="showSubmit"
            type="submit"
            :size="compact ? 'small' : 'medium'"
            class="shrink-0"
        >
            <span>Cari</span>
            <ArrowRight v-if="!compact" class="size-4" />
        </Button>
    </form>
</template>
