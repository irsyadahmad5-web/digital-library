<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{
    modelValue?: boolean;
    class?: HTMLAttributes['class'];
}>(), { modelValue: false });
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const classes = computed(() => cn(
    'relative inline-flex h-6 w-10 shrink-0 rounded-full border border-line bg-surface-subtle transition-colors',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/25',
    'data-[state=checked]:border-brand data-[state=checked]:bg-brand',
    props.class,
));
</script>

<template>
    <SwitchRoot :model-value="modelValue" :class="classes" @update:model-value="emit('update:modelValue', Boolean($event))">
        <SwitchThumb class="block size-5 translate-x-0 rounded-full bg-white shadow-sm transition-transform data-[state=checked]:translate-x-4" />
    </SwitchRoot>
</template>
