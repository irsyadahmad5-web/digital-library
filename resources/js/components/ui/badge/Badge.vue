<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
type Tone = 'neutral' | 'brand' | 'success' | 'warning' | 'danger';
const props = withDefaults(defineProps<{ tone?: Tone; class?: HTMLAttributes['class'] }>(), { tone: 'neutral' });
const classes = computed(() => cn(
    'inline-flex min-h-6 items-center rounded-full px-2.5 text-[11px] font-semibold leading-none',
    props.tone === 'neutral' && 'bg-surface-subtle text-ink-soft',
    props.tone === 'brand' && 'bg-brand-soft text-brand',
    props.tone === 'success' && 'bg-success-soft text-success',
    props.tone === 'warning' && 'bg-warning-soft text-warning',
    props.tone === 'danger' && 'bg-danger-soft text-danger',
    props.class,
));
</script>
<template><span :class="classes"><slot /></span></template>
