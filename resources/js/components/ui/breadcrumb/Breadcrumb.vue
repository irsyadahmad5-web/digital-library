<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
interface Item { label: string; href?: string }
defineProps<{ items: Item[] }>();
</script>
<template>
    <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-1.5 overflow-hidden text-xs text-ink-soft">
        <template v-for="(item, index) in items" :key="`${item.label}-${index}`">
            <ChevronRight v-if="index" class="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
            <Link v-if="item.href && index < items.length - 1" :href="item.href" class="truncate hover:text-ink">{{ item.label }}</Link>
            <span v-else class="truncate" :class="index === items.length - 1 ? 'font-medium text-ink' : ''" :aria-current="index === items.length - 1 ? 'page' : undefined">{{ item.label }}</span>
        </template>
    </nav>
</template>
