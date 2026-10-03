<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost';
type Size = 'medium' | 'large' | 'icon';

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
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[var(--radius-md)] font-medium transition-colors',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/35 focus-visible:ring-offset-2',
    'disabled:pointer-events-none disabled:opacity-50',
    props.variant === 'primary' && 'bg-primary text-primary-foreground hover:bg-primary-hover',
    props.variant === 'secondary' && 'border border-border bg-surface text-foreground hover:bg-muted',
    props.variant === 'ghost' && 'text-foreground hover:bg-muted',
    props.size === 'medium' && 'min-h-11 px-4 py-3 text-sm',
    props.size === 'large' && 'min-h-[52px] px-5 py-4 text-[15px]',
    props.size === 'icon' && 'size-11',
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