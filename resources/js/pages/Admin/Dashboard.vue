<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BarChart3, BookOpen, Database, Download, LibraryBig, ShieldCheck } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { SharedPageProps } from '@/types';

defineProps<{
    summary: {
        total_ebooks: number;
        public_ebooks: number;
        reader_opens: number;
        downloads: number;
        local_storage_bytes: number;
        last_30_days: {
            page_views: number;
            reader_opens: number;
            downloads: number;
        };
    };
}>();

const page = usePage<SharedPageProps>();

function formatBytes(bytes: number) {
    if (bytes <= 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const power = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );

    return (bytes / (1024 ** power)).toFixed(power > 1 ? 1 : 0) + ' ' + units[power];
}
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-primary">Admin</p>
                    <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                        Selamat datang, {{ page.props.auth.user?.name }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Ringkasan koleksi dan aktivitas agregat perpustakaan.
                    </p>
                </div>

                <Link
                    v-if="page.props.auth.user?.permissions.includes('admin.view-analytics')"
                    href="/admin/analytics"
                    class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-border bg-surface px-4 text-sm font-medium hover:bg-muted"
                >
                    <BarChart3 class="size-4" />
                    Buka Analytics
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Total Ebook</p>
                        <BookOpen class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ summary.total_ebooks.toLocaleString('id-ID') }}</p>
                </article>

                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Ebook Publik</p>
                        <LibraryBig class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ summary.public_ebooks.toLocaleString('id-ID') }}</p>
                </article>

                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Pembukaan Reader</p>
                        <LibraryBig class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ summary.reader_opens.toLocaleString('id-ID') }}</p>
                </article>

                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Download</p>
                        <Download class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ summary.downloads.toLocaleString('id-ID') }}</p>
                </article>

                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Storage Lokal</p>
                        <Database class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ formatBytes(summary.local_storage_bytes) }}</p>
                </article>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_.8fr]">
                <section class="rounded-2xl border border-border bg-surface p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <BarChart3 class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-semibold">Aktivitas 30 hari terakhir</h2>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-muted p-4">
                                    <p class="text-xs text-muted-foreground">Page views</p>
                                    <p class="mt-1 text-xl font-semibold">{{ summary.last_30_days.page_views.toLocaleString('id-ID') }}</p>
                                </div>
                                <div class="rounded-xl bg-muted p-4">
                                    <p class="text-xs text-muted-foreground">Reader</p>
                                    <p class="mt-1 text-xl font-semibold">{{ summary.last_30_days.reader_opens.toLocaleString('id-ID') }}</p>
                                </div>
                                <div class="rounded-xl bg-muted p-4">
                                    <p class="text-xs text-muted-foreground">Download</p>
                                    <p class="mt-1 text-xl font-semibold">{{ summary.last_30_days.downloads.toLocaleString('id-ID') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-surface p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                            <ShieldCheck class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold">Privacy-first analytics</h2>
                            <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                Statistik disimpan sebagai angka agregat harian. Tidak ada IP, user-agent,
                                identitas pembaca, kata kunci pencarian, atau progres membaca personal yang disimpan.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
