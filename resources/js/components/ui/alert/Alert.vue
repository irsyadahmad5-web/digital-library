<script setup lang="ts">
import { CircleAlert, CircleCheck, Info, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
type Tone = 'info' | 'success' | 'warning' | 'danger';
const props = withDefaults(defineProps<{ tone?: Tone; title?: string }>(), { tone: 'info' });
const icon = computed(() => ({ info: Info, success: CircleCheck, warning: TriangleAlert, danger: CircleAlert })[props.tone]);
</script>
<template>
    <div role="status" class="flex gap-3 rounded-[var(--radius-lg)] border p-4 text-sm" :class="{
        'border-brand/20 bg-info-soft': tone === 'info',
        'border-success/20 bg-success-soft': tone === 'success',
        'border-warning/20 bg-warning-soft': tone === 'warning',
        'border-danger/20 bg-danger-soft': tone === 'danger',
    }">
        <component :is="icon" class="mt-0.5 size-4 shrink-0" :class="{
            'text-brand': tone === 'info', 'text-success': tone === 'success', 'text-warning': tone === 'warning', 'text-danger': tone === 'danger'
        }" />
        <div class="min-w-0"><p v-if="title" class="font-semibold text-ink">{{ title }}</p><div class="text-ink-soft" :class="title ? 'mt-1' : ''"><slot /></div></div>
    </div>
</template>
