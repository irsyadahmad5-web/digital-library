<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
defineProps<{ class?: HTMLAttributes['class'] }>();
</script>
<template><div aria-hidden="true" :class="cn('animate-pulse rounded-[var(--radius-md)] bg-surface-subtle', $props.class)" /></template>
