<script setup lang="ts">
import { X } from '@lucide/vue';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
const props = withDefaults(defineProps<{ open?: boolean; title: string; description?: string; side?: 'left' | 'right' | 'bottom' }>(), { open: false, side: 'right' });
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
</script>
<template>
    <DialogRoot :open="props.open" @update:open="emit('update:open', $event)">
        <slot name="trigger" />
        <DialogPortal>
            <DialogOverlay class="ui-overlay fixed inset-0 z-50" />
            <DialogContent
                class="fixed z-50 box-border flex min-w-0 max-w-full flex-col overflow-hidden border-line bg-surface text-ink shadow-[var(--shadow-overlay)]"
                :class="{
                    'inset-y-0 left-0 w-[min(90vw,23rem)] border-r p-4 sm:p-5': side === 'left',
                    'inset-y-0 right-0 w-[min(90vw,23rem)] border-l p-4 sm:p-5': side === 'right',
                    'inset-x-0 bottom-0 max-h-[88dvh] rounded-t-[var(--radius-2xl)] border-t p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]': side === 'bottom',
                }"
            >
                <div class="min-w-0 shrink-0 pr-10"><DialogTitle class="text-base font-semibold">{{ title }}</DialogTitle><DialogDescription v-if="description" class="mt-1 text-sm leading-6 text-ink-soft">{{ description }}</DialogDescription></div>
                <DialogClose class="absolute right-4 top-4 grid size-11 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink" aria-label="Tutup panel"><X class="size-4" /></DialogClose>
                <div class="mt-5 min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"><slot /></div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
