<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
withDefaults(defineProps<{ align?: 'start' | 'center' | 'end' }>(), { align: 'end' });
</script>
<template>
    <DropdownMenuRoot>
        <DropdownMenuTrigger as-child><slot name="trigger" /></DropdownMenuTrigger>
        <DropdownMenuPortal><DropdownMenuContent :align="align" :side-offset="8" class="z-[65] min-w-48 rounded-[var(--radius-lg)] border border-line bg-surface p-1.5 text-sm text-ink shadow-[var(--shadow-float)]"><slot /></DropdownMenuContent></DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
