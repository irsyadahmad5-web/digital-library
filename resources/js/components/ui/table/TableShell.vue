<template>
    <div class="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface">
        <div v-if="$slots.toolbar" class="border-b border-line px-4 py-3"><slot name="toolbar" /></div>
        <div class="overflow-x-auto"><slot /></div>
        <div v-if="$slots.footer" class="border-t border-line px-4 py-3"><slot name="footer" /></div>
    </div>
</template>
