<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
const props = withDefaults(defineProps<{
    as?: 'div' | 'article' | 'section';
    tone?: 'default' | 'subtle' | 'elevated';
    padding?: 'none' | 'compact' | 'normal';
    class?: HTMLAttributes['class'];
}>(), { as: 'div', tone: 'default', padding: 'normal' });
const classes = computed(() => cn(
    'rounded-[var(--radius-lg)] border border-line',
    props.tone === 'default' && 'bg-surface',
    props.tone === 'subtle' && 'bg-surface-subtle',
    props.tone === 'elevated' && 'bg-surface-elevated shadow-[var(--shadow-float)]',
    props.padding === 'compact' && 'p-4',
    props.padding === 'normal' && 'p-5 sm:p-6',
    props.class,
));
</script>
<template><component :is="as" :class="classes"><slot /></component></template>
