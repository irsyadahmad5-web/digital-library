<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { BookOpen, Construction } from '@lucide/vue';
import type { SharedPageProps } from '@/types';

defineProps<{
    message: string;
    contactText: string | null;
}>();

const page = usePage<SharedPageProps>();
</script>

<template>
    <Head title="Maintenance" />

    <main class="flex min-h-dvh items-center justify-center bg-background px-5 py-12 text-foreground">
        <section class="w-full max-w-2xl rounded-3xl border border-border bg-surface p-7 text-center shadow-sm sm:p-12">
            <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <Construction class="size-7" />
            </div>

            <div class="mt-6 flex items-center justify-center gap-2 text-sm font-medium text-muted-foreground">
                <BookOpen class="size-4" />
                {{ page.props.site.general.site_name || 'Digital Library' }}
            </div>

            <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
                Sedang dalam pemeliharaan
            </h1>

            <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-muted-foreground">
                {{ message }}
            </p>

            <p v-if="contactText" class="mt-6 text-sm font-medium">
                {{ contactText }}
            </p>
        </section>
    </main>
</template>
