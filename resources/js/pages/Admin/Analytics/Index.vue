<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    Download,
    Eye,
    LibraryBig,
} from '@lucide/vue';
import { Alert } from '@/components/ui/alert';
import { PageHeader } from '@/components/ui/page-header';
import { Stat } from '@/components/ui/stat';
import { TableShell } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface AnalyticsSummary {
    page_views: number;
    home_views: number;
    catalog_views: number;
    directory_views: number;
    book_views: number;
    reader_opens: number;
    info_views: number;
    downloads: number;
}

interface TrendPoint {
    date: string;
    page_views: number;
    book_views: number;
    reader_opens: number;
    downloads: number;
}

interface TopBook {
    id: number;
    title: string;
    slug: string;
    detail_views: number;
    reader_opens: number;
    downloads: number;
}

interface TopTaxonomy {
    id: number;
    name: string;
    slug: string;
    detail_views: number;
    reader_opens: number;
    downloads: number;
}

const props = defineProps<{
    analytics: {
        range: number;
        start_date: string;
        end_date: string;
        summary: AnalyticsSummary;
        trend: TrendPoint[];
        top_books: TopBook[];
        top_categories: TopTaxonomy[];
        top_collections: TopTaxonomy[];
    };
    tracking: {
        enabled: boolean;
        track_page_views: boolean;
        track_reader_opens: boolean;
        track_downloads: boolean;
    };
}>();

const ranges = [7, 30, 90, 365];

const maxPageViews = computed(() =>
    Math.max(1, ...props.analytics.trend.map((item) => item.page_views)),
);

function changeRange(event: Event) {
    const value = Number((event.target as HTMLSelectElement).value);

    router.get('/admin/analytics', { range: value }, {
        preserveScroll: true,
        preserveState: false,
        replace: true,
    });
}

function barHeight(value: number) {
    if (value <= 0) return 3;

    return Math.max(8, Math.round((value / maxPageViews.value) * 100));
}

function chartWidth() {
    return Math.max(props.analytics.trend.length * 10, 720) + 'px';
}

function engagement(item: TopBook | TopTaxonomy) {
    return item.detail_views + (item.reader_opens * 3) + (item.downloads * 4);
}
</script>

<template>
    <Head title="Statistics & Analytics" />

    <AdminLayout>
        <div class="grid gap-5">
            <PageHeader
                eyebrow="Insight Perpustakaan"
                title="Statistics & Analytics"
                description="Statistik agregat untuk memahami trafik, pembacaan, dan download tanpa menyimpan identitas pengunjung."
            >
                <template #actions>
                    <label class="flex min-h-10 items-center gap-2 rounded-[var(--radius-md)] border border-line bg-surface px-3 text-xs">
                        <span class="font-medium text-ink-soft">Periode</span>
                        <select
                            :value="analytics.range"
                            class="bg-transparent font-semibold text-ink outline-none"
                            aria-label="Pilih periode analytics"
                            @change="changeRange"
                        >
                            <option v-for="range in ranges" :key="range" :value="range">
                                {{ range }} hari
                            </option>
                        </select>
                    </label>
                </template>
            </PageHeader>

            <Alert
                :tone="tracking.enabled ? 'success' : 'warning'"
                :title="tracking.enabled ? 'Analytics agregat aktif' : 'Analytics agregat nonaktif'"
            >
                Page view: {{ tracking.track_page_views ? 'aktif' : 'nonaktif' }} ·
                Reader: {{ tracking.track_reader_opens ? 'aktif' : 'nonaktif' }} ·
                Download: {{ tracking.track_downloads ? 'aktif' : 'nonaktif' }}.
            </Alert>

            <section class="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface">
                <div class="grid sm:grid-cols-2 xl:grid-cols-4">
                    <div class="border-b border-line p-4 sm:border-r xl:border-b-0">
                        <Stat label="Kunjungan halaman" :value="analytics.summary.page_views.toLocaleString('id-ID')">
                            <template #icon><Eye class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                    <div class="border-b border-line p-4 xl:border-b-0 xl:border-r">
                        <Stat label="Detail ebook" :value="analytics.summary.book_views.toLocaleString('id-ID')">
                            <template #icon><BookOpen class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                    <div class="border-b border-line p-4 sm:border-r xl:border-b-0">
                        <Stat label="Pembukaan reader" :value="analytics.summary.reader_opens.toLocaleString('id-ID')">
                            <template #icon><LibraryBig class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                    <div class="p-4">
                        <Stat label="Download" :value="analytics.summary.downloads.toLocaleString('id-ID')">
                            <template #icon><Download class="size-4 text-brand" /></template>
                        </Stat>
                    </div>
                </div>

                <div class="grid border-t border-line sm:grid-cols-4">
                    <div class="px-4 py-3 sm:border-r sm:border-line">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Beranda</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-ink">{{ analytics.summary.home_views.toLocaleString('id-ID') }}</p>
                    </div>
                    <div class="px-4 py-3 sm:border-r sm:border-line">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Katalog</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-ink">{{ analytics.summary.catalog_views.toLocaleString('id-ID') }}</p>
                    </div>
                    <div class="px-4 py-3 sm:border-r sm:border-line">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Direktori</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-ink">{{ analytics.summary.directory_views.toLocaleString('id-ID') }}</p>
                    </div>
                    <div class="px-4 py-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Halaman info</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-ink">{{ analytics.summary.info_views.toLocaleString('id-ID') }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-[var(--radius-lg)] border border-line bg-surface p-4 sm:p-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-ink">Tren trafik</h2>
                        <p class="mt-1 text-xs text-ink-soft">{{ analytics.start_date }} — {{ analytics.end_date }}</p>
                    </div>
                    <span class="text-[11px] text-ink-faint">Page views per hari</span>
                </div>

                <div class="mt-5 overflow-x-auto pb-1">
                    <div
                        class="flex h-44 min-w-full items-end gap-1"
                        :style="{ width: chartWidth() }"
                    >
                        <div
                            v-for="point in analytics.trend"
                            :key="point.date"
                            class="group relative flex h-full min-w-[6px] flex-1 items-end"
                        >
                            <div
                                class="w-full rounded-t-sm bg-brand/60 transition-colors group-hover:bg-brand"
                                :style="{ height: barHeight(point.page_views) + '%' }"
                            />
                            <div
                                class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-[var(--radius-md)] border border-line bg-surface px-3 py-2 text-xs shadow-[var(--shadow-float)] group-hover:block"
                            >
                                <p class="font-semibold text-ink">{{ point.date }}</p>
                                <p class="mt-1 text-ink-soft">
                                    {{ point.page_views }} view · {{ point.reader_opens }} baca · {{ point.downloads }} download
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,.65fr)]">
                <TableShell>
                    <template #toolbar>
                        <div>
                            <h2 class="text-sm font-semibold text-ink">Ebook dengan engagement tertinggi</h2>
                            <p class="mt-1 text-xs leading-5 text-ink-soft">
                                Gabungan detail view, pembukaan reader, dan download pada periode terpilih.
                            </p>
                        </div>
                    </template>

                    <table v-if="analytics.top_books.length" class="min-w-full text-left text-xs">
                        <thead class="bg-surface-subtle text-[10px] uppercase tracking-[0.06em] text-ink-faint">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Ebook</th>
                                <th class="px-3 py-3 text-right font-semibold">Detail</th>
                                <th class="px-3 py-3 text-right font-semibold">Baca</th>
                                <th class="px-3 py-3 text-right font-semibold">Download</th>
                                <th class="px-4 py-3 text-right font-semibold">Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="book in analytics.top_books" :key="book.id" class="hover:bg-surface-subtle/60">
                                <td class="px-4 py-3">
                                    <Link
                                        :href="'/book/' + book.slug"
                                        target="_blank"
                                        class="font-semibold text-ink hover:text-brand"
                                    >
                                        {{ book.title }}
                                    </Link>
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums text-ink-soft">{{ book.detail_views }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-ink-soft">{{ book.reader_opens }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-ink-soft">{{ book.downloads }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-ink">{{ engagement(book) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-else class="px-5 py-10 text-center text-xs text-ink-soft">
                        Belum ada engagement ebook pada periode ini.
                    </div>
                </TableShell>

                <div class="grid gap-4">
                    <section class="rounded-[var(--radius-lg)] border border-line bg-surface p-4">
                        <h2 class="text-sm font-semibold text-ink">Kategori populer</h2>
                        <div v-if="analytics.top_categories.length" class="mt-3 divide-y divide-line">
                            <div
                                v-for="(item, index) in analytics.top_categories"
                                :key="item.id"
                                class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-ink">{{ index + 1 }}. {{ item.name }}</p>
                                    <p class="mt-0.5 text-[11px] text-ink-faint">{{ item.reader_opens }} baca · {{ item.downloads }} download</p>
                                </div>
                                <span class="text-xs font-semibold tabular-nums text-ink-soft">{{ engagement(item) }}</span>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-xs text-ink-soft">Belum ada data kategori.</p>
                    </section>

                    <section class="rounded-[var(--radius-lg)] border border-line bg-surface p-4">
                        <h2 class="text-sm font-semibold text-ink">Koleksi populer</h2>
                        <div v-if="analytics.top_collections.length" class="mt-3 divide-y divide-line">
                            <div
                                v-for="(item, index) in analytics.top_collections"
                                :key="item.id"
                                class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-ink">{{ index + 1 }}. {{ item.name }}</p>
                                    <p class="mt-0.5 text-[11px] text-ink-faint">{{ item.reader_opens }} baca · {{ item.downloads }} download</p>
                                </div>
                                <span class="text-xs font-semibold tabular-nums text-ink-soft">{{ engagement(item) }}</span>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-xs text-ink-soft">Belum ada data koleksi.</p>
                    </section>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
