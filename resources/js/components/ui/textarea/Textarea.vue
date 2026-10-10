<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{
    modelValue?: string | null;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>(), { modelValue: '', invalid: false });
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const classes = computed(() => cn(
    'ui-control ui-focus-ring min-h-28 w-full resize-y px-3 py-2.5 text-sm leading-6 placeholder:text-ink-faint',
    props.invalid && 'border-danger focus:border-danger',
    props.class,
));
</script>

<template>
    <textarea
        v-bind="$attrs"
        :value="modelValue ?? ''"
        :aria-invalid="invalid || undefined"
        :class="classes"
        @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
</template>
