<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReaderLayout from '@/layouts/ReaderLayout.vue';
</script>

<template>
    <Head title="Reader" />

    <ReaderLayout>
        <div class="mx-auto flex min-h-dvh max-w-5xl items-center justify-center px-4 py-8 sm:px-8">
            <div class="aspect-[1/1.414] w-full max-w-3xl rounded-md bg-white shadow-2xl" />
        </div>

        <template #controls>
            <div class="flex items-center justify-between gap-4 text-sm">
                <span>← Ebook</span>
                <span>− &nbsp; 100% &nbsp; + &nbsp;&nbsp; 1 / 1 &nbsp;&nbsp; Mode &nbsp;&nbsp; ⛶</span>
            </div>
        </template>
    </ReaderLayout>
</template>