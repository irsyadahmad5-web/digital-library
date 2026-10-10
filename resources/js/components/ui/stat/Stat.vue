<script setup lang="ts">
defineProps<{ label: string; value: string | number; hint?: string }>();
</script>
<template>
    <div class="min-w-0">
        <div class="flex items-center justify-between gap-3"><p class="text-xs font-medium text-ink-soft">{{ label }}</p><slot name="icon" /></div>
        <p class="mt-1.5 truncate text-2xl font-semibold tracking-tight text-ink">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-ink-faint">{{ hint }}</p>
    </div>
</template>
