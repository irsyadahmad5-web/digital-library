<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{
    modelValue?: string | number;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>(), { modelValue: '', invalid: false });
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const classes = computed(() => cn(
    'ui-control ui-focus-ring w-full appearance-none px-3 pr-9 text-sm',
    'bg-[linear-gradient(45deg,transparent_50%,var(--ink-soft)_50%),linear-gradient(135deg,var(--ink-soft)_50%,transparent_50%)] bg-[position:calc(100%-15px)_50%,calc(100%-10px)_50%] bg-[size:5px_5px,5px_5px] bg-no-repeat',
    props.invalid && 'border-danger focus:border-danger',
    props.class,
));
</script>

<template>
    <select
        v-bind="$attrs"
        :value="modelValue"
        :aria-invalid="invalid || undefined"
        :class="classes"
        @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
        <slot />
    </select>
</template>
