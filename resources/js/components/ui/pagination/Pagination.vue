<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
const props = defineProps<{ page: number; lastPage: number }>();
const emit = defineEmits<{ change: [page: number] }>();
const pages = computed(() => {
    const values = new Set<number>([1, props.lastPage, props.page - 1, props.page, props.page + 1]);
    return [...values].filter((value) => value >= 1 && value <= props.lastPage).sort((a, b) => a - b);
});
</script>
<template>
    <nav v-if="lastPage > 1" aria-label="Pagination" class="flex items-center justify-between gap-3">
        <button type="button" class="grid size-10 place-items-center rounded-[var(--radius-md)] border border-line bg-surface text-ink-soft disabled:opacity-40" :disabled="page <= 1" aria-label="Halaman sebelumnya" @click="emit('change', page - 1)"><ChevronLeft class="size-4" /></button>
        <div class="flex items-center gap-1">
            <button v-for="value in pages" :key="value" type="button" class="min-w-9 rounded-[var(--radius-md)] px-2 py-2 text-xs font-semibold" :class="value === page ? 'bg-brand text-brand-foreground' : 'text-ink-soft hover:bg-surface-subtle hover:text-ink'" :aria-current="value === page ? 'page' : undefined" @click="emit('change', value)">{{ value }}</button>
        </div>
        <button type="button" class="grid size-10 place-items-center rounded-[var(--radius-md)] border border-line bg-surface text-ink-soft disabled:opacity-40" :disabled="page >= lastPage" aria-label="Halaman berikutnya" @click="emit('change', page + 1)"><ChevronRight class="size-4" /></button>
    </nav>
</template>
