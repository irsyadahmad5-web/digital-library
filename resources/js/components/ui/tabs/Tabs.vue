<script setup lang="ts">
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
interface TabItem { value: string; label: string; disabled?: boolean }
const props = defineProps<{ modelValue: string; items: TabItem[] }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
</script>
<template>
    <TabsRoot :model-value="props.modelValue" @update:model-value="emit('update:modelValue', String($event))">
        <TabsList class="inline-flex min-h-10 max-w-full items-center gap-1 overflow-x-auto rounded-[var(--radius-md)] bg-surface-subtle p-1">
            <TabsTrigger v-for="item in items" :key="item.value" :value="item.value" :disabled="item.disabled" class="min-h-8 whitespace-nowrap rounded-[7px] px-3 text-xs font-semibold text-ink-soft outline-none transition-colors data-[state=active]:bg-surface data-[state=active]:text-ink data-[state=active]:shadow-sm focus-visible:ring-2 focus-visible:ring-focus/25">{{ item.label }}</TabsTrigger>
        </TabsList>
        <TabsContent v-for="item in items" :key="item.value" :value="item.value" class="mt-4 outline-none focus-visible:ring-2 focus-visible:ring-focus/25"><slot :name="`panel-${item.value}`" /></TabsContent>
    </TabsRoot>
</template>
