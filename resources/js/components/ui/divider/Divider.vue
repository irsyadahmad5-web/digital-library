<script setup lang="ts">
withDefaults(defineProps<{ orientation?: 'horizontal' | 'vertical' }>(), { orientation: 'horizontal' });
</script>
<template><div role="separator" :aria-orientation="orientation" :class="orientation === 'horizontal' ? 'h-px w-full bg-line' : 'h-full w-px bg-line'" /></template>
