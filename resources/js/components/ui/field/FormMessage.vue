<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'neutral' | 'success' | 'warning' | 'danger' }>(), { tone: 'neutral' });
</script>

<template>
    <p
        class="text-xs leading-5"
        :class="{
            'text-ink-soft': tone === 'neutral',
            'text-success': tone === 'success',
            'text-warning': tone === 'warning',
            'text-danger': tone === 'danger',
        }"
    ><slot /></p>
</template>
