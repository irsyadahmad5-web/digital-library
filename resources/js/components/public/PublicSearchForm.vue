<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(defineProps<{
    initialQuery?: string;
    placeholder?: string;
    compact?: boolean;
}>(), {
    initialQuery: '',
    placeholder: 'Cari judul, penulis, kategori, tag, penerbit, koleksi, atau ISBN...',
    compact: false,
});

const query = ref(props.initialQuery);

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
        class="flex items-center gap-3 rounded-2xl border border-border bg-surface"
        :class="compact ? 'px-3 py-2.5' : 'px-4 py-3'"
        role="search"
        @submit.prevent="submit"
    >
        <Search class="size-5 shrink-0 text-muted-foreground" />
        <input
            v-model="query"
            type="search"
            class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            :placeholder="placeholder"
            aria-label="Cari ebook"
        >
        <Button type="submit" :size="compact ? 'medium' : 'large'">
            Cari
        </Button>
    </form>
</template>
