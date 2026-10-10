<script setup lang="ts">
import { X } from '@lucide/vue';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
const props = withDefaults(defineProps<{ open?: boolean; title: string; description?: string; closeLabel?: string }>(), { open: false, closeLabel: 'Tutup dialog' });
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
</script>
<template>
    <DialogRoot :open="props.open" @update:open="emit('update:open', $event)">
        <slot name="trigger" />
        <DialogPortal>
            <DialogOverlay class="ui-overlay fixed inset-0 z-50" />
            <DialogContent class="fixed left-1/2 top-1/2 z-50 w-[min(92vw,34rem)] -translate-x-1/2 -translate-y-1/2 rounded-[var(--radius-xl)] border border-line bg-surface p-5 text-ink shadow-[var(--shadow-overlay)] sm:p-6">
                <div class="pr-10"><DialogTitle class="text-lg font-semibold tracking-tight">{{ title }}</DialogTitle><DialogDescription v-if="description" class="mt-1.5 text-sm leading-6 text-ink-soft">{{ description }}</DialogDescription></div>
                <DialogClose class="absolute right-4 top-4 grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink" :aria-label="closeLabel"><X class="size-4" /></DialogClose>
                <div class="mt-5"><slot /></div>
                <div v-if="$slots.footer" class="mt-6 flex flex-wrap justify-end gap-2"><slot name="footer" /></div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
