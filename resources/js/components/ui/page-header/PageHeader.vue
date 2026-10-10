<script setup lang="ts">
defineProps<{ eyebrow?: string; title: string; description?: string }>();
</script>
<template>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.12em] text-brand">{{ eyebrow }}</p>
            <h1 class="text-balance text-2xl font-semibold tracking-tight text-ink sm:text-3xl" :class="eyebrow ? 'mt-1.5' : ''">{{ title }}</h1>
            <p v-if="description" class="mt-2 max-w-2xl text-sm leading-6 text-ink-soft">{{ description }}</p>
            <slot />
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2"><slot name="actions" /></div>
    </header>
</template>
