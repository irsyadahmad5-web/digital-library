<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue: string | number;
    value: string | number;
    class?: HTMLAttributes['class'];
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string | number] }>();
const checked = computed(() => String(props.modelValue) === String(props.value));
</script>

<template>
    <button
        type="button"
        role="radio"
        :aria-checked="checked"
        :class="cn('grid size-5 shrink-0 place-items-center rounded-full border border-line bg-surface focus-visible:ring-2 focus-visible:ring-focus/25', checked && 'border-brand', props.class)"
        @click="emit('update:modelValue', value)"
    >
        <span v-if="checked" class="size-2.5 rounded-full bg-brand" />
    </button>
</template>
