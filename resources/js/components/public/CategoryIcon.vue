<script setup lang="ts">
import { computed } from 'vue';
import { categoryIconComponent } from '@/lib/category-icons';

const props = withDefaults(defineProps<{
    name?: string | null;
    sizeClass?: string;
}>(), {
    name: 'BookOpen',
    sizeClass: 'size-4',
});

const component = computed(() => categoryIconComponent(props.name));
</script>

<template>
    <component :is="component" :class="sizeClass" aria-hidden="true" />
</template>
