<script setup lang="ts">
const props = defineProps<{
    label?: string;
    inputId?: string;
    hint?: string;
    error?: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="grid gap-1.5">
        <div v-if="label || $slots.label" class="flex items-baseline justify-between gap-3">
            <label :for="props.inputId" class="text-sm font-medium text-ink">
                <slot name="label">{{ label }}</slot>
                <span v-if="required" class="ml-0.5 text-danger" aria-hidden="true">*</span>
            </label>
            <slot name="aside" />
        </div>
        <slot />
        <p v-if="error" class="text-xs leading-5 text-danger" role="alert">{{ error }}</p>
        <p v-else-if="hint" class="text-xs leading-5 text-ink-soft">{{ hint }}</p>
    </div>
</template>
