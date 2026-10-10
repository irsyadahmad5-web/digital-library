<script setup lang="ts">
import { Check } from '@lucide/vue';
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{
    modelValue?: boolean;
    class?: HTMLAttributes['class'];
}>(), { modelValue: false });
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
const classes = computed(() => cn(
    'flex size-5 shrink-0 items-center justify-center rounded-[6px] border border-line bg-surface text-brand-foreground transition-colors',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/25',
    'data-[state=checked]:border-brand data-[state=checked]:bg-brand',
    props.class,
));
</script>

<template>
    <CheckboxRoot
        :model-value="modelValue"
        :class="classes"
        @update:model-value="emit('update:modelValue', Boolean($event))"
    >
        <CheckboxIndicator class="grid place-items-center">
            <Check class="size-3.5" :stroke-width="2.5" />
        </CheckboxIndicator>
    </CheckboxRoot>
</template>
