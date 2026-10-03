<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border/70 bg-surface/95 backdrop-blur">
            <div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between px-5 sm:px-8">
                <Link href="/" class="text-lg font-semibold tracking-tight">
                    Digital Library
                </Link>

                <nav class="hidden items-center gap-8 text-sm text-muted-foreground md:flex">
                    <Link href="/" class="transition-colors hover:text-foreground">Koleksi</Link>
                    <span>Kategori</span>
                    <span>Penulis</span>
                    <span class="inline-flex items-center gap-2">
                        <Search class="size-4" />
                        Cari
                    </span>
                </nav>
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>