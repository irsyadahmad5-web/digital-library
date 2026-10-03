<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

const stats = [
    ['Total Ebook', '0'],
    ['Dibaca', '0'],
    ['Download', '0'],
    ['Storage', '0 GB'],
];
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div>
            <p class="text-sm font-medium text-primary">Admin</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Dashboard</h1>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="[label, value] in stats"
                :key="label"
                class="rounded-2xl border border-border bg-surface p-5"
            >
                <p class="text-sm text-muted-foreground">{{ label }}</p>
                <p class="mt-2 text-3xl font-semibold">{{ value }}</p>
            </article>
        </div>

        <div class="mt-6 min-h-80 rounded-2xl border border-border bg-surface p-6">
            <p class="font-semibold">Fondasi admin siap</p>
            <p class="mt-2 text-sm text-muted-foreground">
                Modul autentikasi dan data admin akan dibangun pada stage berikutnya.
            </p>
        </div>
    </AdminLayout>
</template>