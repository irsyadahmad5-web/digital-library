<script setup lang="ts">
import { TooltipContent, TooltipPortal, TooltipProvider, TooltipRoot, TooltipTrigger } from 'reka-ui';
withDefaults(defineProps<{ text: string; side?: 'top' | 'right' | 'bottom' | 'left' }>(), { side: 'top' });
</script>
<template>
    <TooltipProvider :delay-duration="300">
        <TooltipRoot>
            <TooltipTrigger as-child><slot /></TooltipTrigger>
            <TooltipPortal><TooltipContent :side="side" :side-offset="7" class="z-[70] max-w-64 rounded-[var(--radius-sm)] bg-ink px-2.5 py-1.5 text-xs text-white shadow-[var(--shadow-float)]">{{ text }}</TooltipContent></TooltipPortal>
        </TooltipRoot>
    </TooltipProvider>
</template>
