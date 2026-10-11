<script setup lang="ts">
import { CheckCircle2, CircleAlert, Info, X } from '@lucide/vue';
import { computed } from 'vue';
type Tone = 'info' | 'success' | 'danger';
const props = withDefaults(defineProps<{ show: boolean; tone?: Tone; title?: string; message: string }>(), { tone: 'info' });
const emit = defineEmits<{ close: [] }>();
const icon = computed(() => ({ info: Info, success: CheckCircle2, danger: CircleAlert })[props.tone]);
</script>
<template>
    <div v-if="show" role="status" aria-live="polite" class="flex w-[min(92vw,24rem)] gap-3 rounded-[var(--radius-lg)] border border-line bg-surface p-4 text-sm text-ink shadow-[var(--shadow-float)]">
        <component :is="icon" class="mt-0.5 size-4 shrink-0" :class="{ 'text-brand': tone === 'info', 'text-success': tone === 'success', 'text-danger': tone === 'danger' }" />
        <div class="min-w-0 flex-1"><p v-if="title" class="font-semibold">{{ title }}</p><p class="text-ink-soft" :class="title ? 'mt-1' : ''">{{ message }}</p></div>
        <button type="button" class="grid size-11 shrink-0 place-items-center rounded-[var(--radius-sm)] text-ink-soft hover:bg-surface-subtle hover:text-ink sm:size-8" aria-label="Tutup notifikasi" @click="emit('close')"><X class="size-4" /></button>
    </div>
</template>
