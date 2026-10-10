<script setup lang="ts">
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
withDefaults(defineProps<{ align?: 'start' | 'center' | 'end'; side?: 'top' | 'right' | 'bottom' | 'left' }>(), { align: 'end', side: 'bottom' });
</script>
<template>
    <PopoverRoot>
        <PopoverTrigger as-child><slot name="trigger" /></PopoverTrigger>
        <PopoverPortal><PopoverContent :align="align" :side="side" :side-offset="8" class="z-[65] min-w-48 rounded-[var(--radius-lg)] border border-line bg-surface p-2 text-sm text-ink shadow-[var(--shadow-float)]"><slot /></PopoverContent></PopoverPortal>
    </PopoverRoot>
</template>
