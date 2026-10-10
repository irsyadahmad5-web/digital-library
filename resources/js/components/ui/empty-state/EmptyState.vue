<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>
<template>
    <div class="mx-auto flex max-w-lg flex-col items-center px-5 py-12 text-center">
        <div v-if="$slots.icon" class="mb-4 grid size-11 place-items-center rounded-[var(--radius-lg)] bg-surface-subtle text-ink-soft"><slot name="icon" /></div>
        <h3 class="text-base font-semibold text-ink">{{ title }}</h3>
        <p v-if="description" class="mt-1.5 text-sm leading-6 text-ink-soft">{{ description }}</p>
        <div v-if="$slots.actions" class="mt-5 flex flex-wrap justify-center gap-2"><slot name="actions" /></div>
    </div>
</template>
