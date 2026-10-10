<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Variant = 'secondary' | 'ghost' | 'quiet' | 'danger';

const props = withDefaults(defineProps<PrimitiveProps & {
    label: string;
    variant?: Variant;
    class?: HTMLAttributes['class'];
}>(), {
    as: 'button',
    variant: 'secondary',
});

const classes = computed(() => cn(
    'inline-flex size-11 shrink-0 items-center justify-center rounded-[var(--radius-md)] transition-colors sm:size-10',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/25 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
    'disabled:pointer-events-none disabled:opacity-50',
    props.variant === 'secondary' && 'border border-line bg-surface text-ink-soft hover:border-line-strong hover:bg-surface-subtle hover:text-ink',
    props.variant === 'ghost' && 'text-ink hover:bg-surface-subtle',
    props.variant === 'quiet' && 'text-ink-soft hover:bg-surface-subtle hover:text-ink',
    props.variant === 'danger' && 'text-danger hover:bg-danger-soft',
    props.class,
));
</script>

<template>
    <Primitive :as="props.as" :as-child="props.asChild" :aria-label="label" :class="classes">
        <slot />
    </Primitive>
</template>
