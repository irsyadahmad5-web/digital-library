<script setup lang="ts">
const menu = [
    'Dashboard',
    'Ebook',
    'Kategori',
    'Penulis',
    'Penerbit',
    'Koleksi',
    'Storage',
    'Analytics',
    'Pengaturan',
];
</script>

<template>
    <div class="min-h-screen bg-background md:grid md:grid-cols-[248px_minmax(0,1fr)]">
        <aside class="hidden border-r border-border bg-surface p-5 md:block">
            <div class="mb-8 text-lg font-semibold">Digital Library</div>

            <nav class="space-y-1">
                <div
                    v-for="(item, index) in menu"
                    :key="item"
                    class="rounded-xl px-4 py-3 text-sm"
                    :class="index === 0 ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-muted'"
                >
                    {{ item }}
                </div>
            </nav>
        </aside>

        <main class="min-w-0 p-5 sm:p-8 lg:p-10">
            <slot />
        </main>
    </div>
</template>