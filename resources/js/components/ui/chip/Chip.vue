<script setup lang="ts">
import { X } from '@lucide/vue';
withDefaults(defineProps<{ removable?: boolean; active?: boolean }>(), { removable: false, active: false });
const emit = defineEmits<{ remove: [] }>();
</script>
<template>
    <span class="inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium" :class="active ? 'border-brand/30 bg-brand-soft text-brand' : 'border-line bg-surface text-ink-soft'">
        <slot />
        <button v-if="removable" type="button" class="-mr-1 grid size-6 place-items-center rounded-full hover:bg-black/5" aria-label="Hapus filter" @click="emit('remove')"><X class="size-3.5" /></button>
    </span>
</template>
