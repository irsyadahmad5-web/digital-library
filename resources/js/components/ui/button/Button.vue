<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'quiet';
type Size = 'small' | 'medium' | 'large' | 'icon';

const props = withDefaults(defineProps<PrimitiveProps & {
    variant?: Variant;
    size?: Size;
    class?: HTMLAttributes['class'];
}>(), {
    as: 'button',
    variant: 'primary',
    size: 'medium',
});

const classes = computed(() => cn(
    'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-[var(--radius-md)] font-semibold transition-[background-color,border-color,color,box-shadow,transform] duration-150',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/25 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
    'disabled:pointer-events-none disabled:opacity-50',
    props.variant === 'primary' && 'bg-brand text-brand-foreground hover:bg-brand-hover',
    props.variant === 'secondary' && 'border border-line bg-surface text-ink hover:border-line-strong hover:bg-surface-subtle',
    props.variant === 'ghost' && 'text-ink hover:bg-surface-subtle',
    props.variant === 'quiet' && 'text-ink-soft hover:bg-surface-subtle hover:text-ink',
    props.variant === 'danger' && 'bg-danger text-white hover:brightness-95',
    props.size === 'small' && 'min-h-9 px-3 text-xs',
    props.size === 'medium' && 'min-h-10 px-4 text-sm',
    props.size === 'large' && 'min-h-11 px-5 text-sm',
    props.size === 'icon' && 'size-11 p-0 sm:size-10',
    props.class,
));
</script>

<template>
    <Primitive
        data-slot="button"
        :as="props.as"
        :as-child="props.asChild"
        :class="classes"
    >
        <slot />
    </Primitive>
</template>
