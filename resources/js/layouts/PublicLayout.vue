<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Search } from '@lucide/vue';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const site = page.props.site;

const siteName = computed(() => String(site.general.site_name || 'Digital Library'));
const logoUrl = computed(() => site.general.logo_url ? String(site.general.logo_url) : null);
const faviconUrl = computed(() => site.general.favicon_url ? String(site.general.favicon_url) : null);

const themeStyle = computed(() => ({
    '--primary': String(site.appearance.primary_color || '#2563EB'),
    '--background': String(site.appearance.background_color || '#FAFAF7'),
    '--content-max-width': `${Number(site.appearance.content_max_width || 1280)}px`,
}));
</script>

<template>
    <div class="min-h-screen bg-background text-foreground" :style="themeStyle">
        <Head>
            <link v-if="faviconUrl" rel="icon" :href="faviconUrl">
        </Head>

        <header class="border-b border-border/70 bg-surface/95 backdrop-blur">
            <div class="mx-auto flex min-h-20 items-center justify-between px-5 sm:px-8" style="max-width: var(--content-max-width)">
                <Link href="/" class="flex items-center gap-3 text-lg font-semibold tracking-tight">
                    <span class="flex size-9 items-center justify-center overflow-hidden rounded-xl bg-primary text-primary-foreground">
                        <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                        <BookOpen v-else class="size-4" />
                    </span>
                    <span>{{ siteName }}</span>
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
