<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    Database,
    Download,
    Eye,
    LibraryBig,
    ShieldCheck,
} from '@lucide/vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/ui/page-header';
import { Stat } from '@/components/ui/stat';
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
        <div class="grid gap-5">
            <PageHeader
                eyebrow="Overview"
                title="Dashboard"
                :description="'Selamat datang, ' + (page.props.auth.user?.name || 'Admin') + '. Ringkasan koleksi dan aktivitas perpustakaan.'"
            >
                <template #actions>
                    <Button
                        v-if="page.props.auth.user?.permissions.includes('admin.view-analytics')"
                        as-child
                        variant="secondary"
                        size="small"
                    >
                        <Link href="/admin/analytics">
                            <BarChart3 class="size-4" />
                            Buka Analytics
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <section class="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface">
                <div class="grid sm:grid-cols-2 xl:grid-cols-5">
                    <div class="border-b border-line p-4 sm:border-r xl:border-b-0">
                        <Stat label="Total Ebook" :value="summary.total_ebooks.toLocaleString('id-ID')">
                            <template #icon><BookOpen class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                    <div class="border-b border-line p-4 xl:border-b-0 xl:border-r">
                        <Stat label="Ebook Publik" :value="summary.public_ebooks.toLocaleString('id-ID')">
                            <template #icon><LibraryBig class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                    <div class="border-b border-line p-4 sm:border-r xl:border-b-0">
                        <Stat label="Pembukaan Reader" :value="summary.reader_opens.toLocaleString('id-ID')">
                            <template #icon><Eye class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                    <div class="border-b border-line p-4 xl:border-b-0 xl:border-r">
                        <Stat label="Download" :value="summary.downloads.toLocaleString('id-ID')">
                            <template #icon><Download class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                    <div class="p-4">
                        <Stat label="Storage Lokal" :value="formatBytes(summary.local_storage_bytes)">
                            <template #icon><Database class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(300px,.75fr)]">
                <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
                    <div class="border-b border-line px-4 py-3.5 sm:px-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-semibold text-ink">Aktivitas 30 hari terakhir</h2>
                                <p class="mt-1 text-xs text-ink-soft">Ringkasan trafik dan interaksi utama.</p>
                            </div>
                            <BarChart3 class="size-4 text-ink-faint" />
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3">
                        <div class="border-b border-line p-4 sm:border-b-0 sm:border-r">
                            <Stat label="Page views" :value="summary.last_30_days.page_views.toLocaleString('id-ID')" />
                        </div>
                        <div class="border-b border-line p-4 sm:border-b-0 sm:border-r">
                            <Stat label="Reader" :value="summary.last_30_days.reader_opens.toLocaleString('id-ID')" />
                        </div>
                        <div class="p-4">
                            <Stat label="Download" :value="summary.last_30_days.downloads.toLocaleString('id-ID')" />
                        </div>
                    </div>
                </section>

                <Alert tone="success" title="Privacy-first analytics" class="self-start">
                    Statistik disimpan sebagai angka agregat harian. Tidak ada IP, user-agent,
                    identitas pembaca, kata kunci pencarian, atau progres membaca personal yang disimpan.
                </Alert>
            </div>
        </div>
    </AdminLayout>
</template>
