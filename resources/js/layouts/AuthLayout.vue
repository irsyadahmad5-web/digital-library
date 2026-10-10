<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    LibraryBig,
    ShieldCheck,
} from '@lucide/vue';
import type { SharedPageProps } from '@/types';

withDefaults(defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
}>(), {
    eyebrow: 'Administration',
    description: '',
});

const page = usePage<SharedPageProps>();
const siteName = computed(() => String(page.props.site.general.site_name || 'Digital Library'));
const tagline = computed(() => String(page.props.site.general.tagline || 'Perpustakaan digital'));
const logoUrl = computed(() => page.props.site.general.logo_url ? String(page.props.site.general.logo_url) : null);
</script>

<template>
    <main class="min-h-dvh bg-canvas text-ink">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(340px,.82fr)_minmax(480px,1.18fr)]">
            <section class="relative hidden overflow-hidden border-r border-line bg-surface-subtle lg:flex lg:flex-col">
                <div class="flex min-h-16 items-center px-7 xl:px-9">
                    <Link href="/" class="inline-flex min-w-0 items-center gap-2.5" aria-label="Kembali ke perpustakaan">
                        <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-[var(--radius-md)] bg-brand text-brand-foreground shadow-sm">
                            <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                            <BookOpen v-else class="size-[17px]" />
                        </span>
                        <span class="max-w-64 truncate text-sm font-semibold tracking-tight text-ink">{{ siteName }}</span>
                    </Link>
                </div>

                <div class="flex flex-1 items-center px-7 py-10 xl:px-9">
                    <div class="max-w-lg">
                        <div class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-brand">
                            <ShieldCheck class="size-4" />
                            Area administrator
                        </div>
                        <h2 class="mt-4 text-balance text-3xl font-semibold leading-[1.15] tracking-[-0.03em] text-ink xl:text-4xl">
                            Kelola perpustakaan dengan fokus dan aman.
                        </h2>
                        <p class="mt-4 max-w-md text-sm leading-6 text-ink-soft">
                            {{ tagline }}. Area administrasi terpisah dari pengalaman pembaca dan hanya untuk akun yang berwenang.
                        </p>

                        <div class="mt-7 grid gap-3 text-xs text-ink-soft">
                            <div class="flex items-center gap-2.5">
                                <span class="grid size-7 place-items-center rounded-[var(--radius-sm)] bg-surface text-brand">
                                    <LibraryBig class="size-3.5" />
                                </span>
                                <span>Kelola koleksi, metadata, tampilan, dan pengaturan dari satu workspace.</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="grid size-7 place-items-center rounded-[var(--radius-sm)] bg-surface text-success">
                                    <ShieldCheck class="size-3.5" />
                                </span>
                                <span>Autentikasi dan sesi admin tetap terpisah dari akses publik.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-7 pb-7 text-[10px] text-ink-faint xl:px-9">
                    {{ siteName }} · Administration
                </div>
            </section>

            <section class="flex min-h-dvh flex-col">
                <header class="flex min-h-16 items-center justify-between gap-3 border-b border-line/70 px-4 sm:px-6 lg:border-b-0 lg:px-8">
                    <Link href="/" class="inline-flex items-center gap-2 text-xs font-semibold text-ink-soft hover:text-ink lg:hidden">
                        <ArrowLeft class="size-4" />
                        Perpustakaan
                    </Link>

                    <Link href="/" class="hidden items-center gap-1.5 text-xs font-semibold text-ink-soft hover:text-ink lg:inline-flex">
                        <ArrowLeft class="size-4" />
                        Kembali ke situs publik
                    </Link>

                    <div class="flex min-w-0 items-center gap-2 lg:hidden">
                        <span class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-[var(--radius-md)] bg-brand text-brand-foreground">
                            <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                            <BookOpen v-else class="size-4" />
                        </span>
                        <span class="max-w-36 truncate text-xs font-semibold text-ink">{{ siteName }}</span>
                    </div>
                </header>

                <div class="flex flex-1 items-center justify-center px-4 py-8 sm:px-6 lg:px-10 lg:py-12">
                    <section class="w-full max-w-md">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-brand">{{ eyebrow }}</p>
                            <h1 class="mt-1.5 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[2rem]">{{ title }}</h1>
                            <p v-if="description" class="mt-2 text-sm leading-6 text-ink-soft">{{ description }}</p>
                        </div>

                        <div class="mt-6 rounded-[var(--radius-xl)] border border-line bg-surface p-5 shadow-[var(--shadow-float)] sm:p-6">
                            <slot />
                        </div>

                        <div v-if="$slots.footer" class="mt-5 text-center text-xs text-ink-soft">
                            <slot name="footer" />
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </main>
</template>
