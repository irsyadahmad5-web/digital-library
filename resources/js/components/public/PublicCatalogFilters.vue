<script setup lang="ts">
import { RotateCcw } from '@lucide/vue';
import { Select } from '@/components/ui/select';
import type { DirectoryItem } from '@/types/public-library';

withDefaults(defineProps<{
    filterOptions: {
        categories: DirectoryItem[];
        authors: DirectoryItem[];
        publishers: DirectoryItem[];
        collections: DirectoryItem[];
        tags: DirectoryItem[];
        languages: DirectoryItem[];
    };
    autoApply?: boolean;
}>(), {
    autoApply: false,
});

const category = defineModel<string>('category', { required: true });
const author = defineModel<string>('author', { required: true });
const publisher = defineModel<string>('publisher', { required: true });
const collection = defineModel<string>('collection', { required: true });
const tag = defineModel<string>('tag', { required: true });
const language = defineModel<string>('language', { required: true });
const year = defineModel<number | null>('year', { required: true });

const emit = defineEmits<{
    apply: [];
    reset: [];
}>();

function maybeApply(autoApply: boolean) {
    if (autoApply) emit('apply');
}
</script>

<template>
    <div class="grid gap-4">
        <label class="grid gap-1.5">
            <span class="text-xs font-semibold text-ink-soft">Kategori</span>
            <Select v-model="category" @change="maybeApply(autoApply)">
                <option value="">Semua kategori</option>
                <option v-for="item in filterOptions.categories" :key="item.slug" :value="item.slug">
                    {{ item.name }} ({{ item.count }})
                </option>
            </Select>
        </label>

        <label class="grid gap-1.5">
            <span class="text-xs font-semibold text-ink-soft">Penulis</span>
            <Select v-model="author" @change="maybeApply(autoApply)">
                <option value="">Semua penulis</option>
                <option v-for="item in filterOptions.authors" :key="item.slug" :value="item.slug">
                    {{ item.name }} ({{ item.count }})
                </option>
            </Select>
        </label>

        <label class="grid gap-1.5">
            <span class="text-xs font-semibold text-ink-soft">Penerbit</span>
            <Select v-model="publisher" @change="maybeApply(autoApply)">
                <option value="">Semua penerbit</option>
                <option v-for="item in filterOptions.publishers" :key="item.slug" :value="item.slug">
                    {{ item.name }} ({{ item.count }})
                </option>
            </Select>
        </label>

        <label class="grid gap-1.5">
            <span class="text-xs font-semibold text-ink-soft">Koleksi</span>
            <Select v-model="collection" @change="maybeApply(autoApply)">
                <option value="">Semua koleksi</option>
                <option v-for="item in filterOptions.collections" :key="item.slug" :value="item.slug">
                    {{ item.name }} ({{ item.count }})
                </option>
            </Select>
        </label>

        <label class="grid gap-1.5">
            <span class="text-xs font-semibold text-ink-soft">Tag</span>
            <Select v-model="tag" @change="maybeApply(autoApply)">
                <option value="">Semua tag</option>
                <option v-for="item in filterOptions.tags" :key="item.slug" :value="item.slug">
                    {{ item.name }} ({{ item.count }})
                </option>
            </Select>
        </label>

        <label class="grid gap-1.5">
            <span class="text-xs font-semibold text-ink-soft">Bahasa</span>
            <Select v-model="language" @change="maybeApply(autoApply)">
                <option value="">Semua bahasa</option>
                <option v-for="item in filterOptions.languages" :key="item.slug" :value="item.slug">
                    {{ item.name }} ({{ item.count }})
                </option>
            </Select>
        </label>

        <label class="grid gap-1.5">
            <span class="text-xs font-semibold text-ink-soft">Tahun terbit</span>
            <input
                v-model.number="year"
                type="number"
                min="1000"
                max="9999"
                inputmode="numeric"
                class="ui-control ui-focus-ring w-full px-3 text-sm placeholder:text-ink-faint"
                placeholder="Contoh: 2026"
                @keyup.enter="emit('apply')"
            >
        </label>

        <button
            type="button"
            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-[var(--radius-md)] text-xs font-semibold text-ink-soft transition-colors hover:bg-surface-subtle hover:text-ink"
            @click="emit('reset')"
        >
            <RotateCcw class="size-3.5" />
            Reset filter
        </button>
    </div>
</template>
