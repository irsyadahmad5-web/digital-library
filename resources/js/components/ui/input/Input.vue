<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = withDefaults(defineProps<{
    modelValue?: string | number | null;
    type?: string;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>(), {
    modelValue: '',
    type: 'text',
    invalid: false,
});

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const classes = computed(() => cn(
    'ui-control ui-focus-ring w-full px-3 text-sm placeholder:text-ink-faint',
    props.invalid && 'border-danger focus:border-danger focus:ring-danger/20',
    props.class,
));
</script>

<template>
    <input
        v-bind="$attrs"
        :type="type"
        :value="modelValue ?? ''"
        :aria-invalid="invalid || undefined"
        :class="classes"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    >
</template>
