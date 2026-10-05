<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BarChart3, BookOpen, Download, Eye, LibraryBig, ShieldCheck } from '@lucide/vue';
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
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <BarChart3 class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-primary">Insight Perpustakaan</p>
                        <h1 class="mt-1 text-3xl font-semibold tracking-tight">Statistics & Analytics</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Statistik agregat untuk memahami trafik, pembacaan, dan download tanpa menyimpan IP,
                            user-agent, identitas pengunjung, maupun kata kunci pencarian.
                        </p>
                    </div>
                </div>

                <label class="flex min-h-11 items-center gap-3 rounded-xl border border-border bg-surface px-4 text-sm">
                    <span class="text-muted-foreground">Periode</span>
                    <select
                        :value="analytics.range"
                        class="bg-transparent font-medium outline-none"
                        aria-label="Pilih periode analytics"
                        @change="changeRange"
                    >
                        <option v-for="range in ranges" :key="range" :value="range">
                            {{ range }} hari
                        </option>
                    </select>
                </label>
            </div>

            <div
                class="mt-6 flex items-start gap-3 rounded-2xl border px-4 py-4 text-sm"
                :class="tracking.enabled
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-amber-200 bg-amber-50 text-amber-800'"
            >
                <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                <div>
                    <p class="font-medium">
                        {{ tracking.enabled ? 'Analytics agregat aktif' : 'Analytics agregat nonaktif' }}
                    </p>
                    <p class="mt-1 leading-6">
                        Page view: {{ tracking.track_page_views ? 'aktif' : 'nonaktif' }} ·
                        Reader: {{ tracking.track_reader_opens ? 'aktif' : 'nonaktif' }} ·
                        Download: {{ tracking.track_downloads ? 'aktif' : 'nonaktif' }}.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Kunjungan halaman</p>
                        <Eye class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold">{{ analytics.summary.page_views.toLocaleString('id-ID') }}</p>
                </article>

                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Detail ebook</p>
                        <BookOpen class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold">{{ analytics.summary.book_views.toLocaleString('id-ID') }}</p>
                </article>

                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Pembukaan reader</p>
                        <LibraryBig class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold">{{ analytics.summary.reader_opens.toLocaleString('id-ID') }}</p>
                </article>

                <article class="rounded-2xl border border-border bg-surface p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">Download</p>
                        <Download class="size-4 text-primary" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold">{{ analytics.summary.downloads.toLocaleString('id-ID') }}</p>
                </article>
            </div>

            <section class="mt-6 rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="font-semibold">Tren trafik</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ analytics.start_date }} — {{ analytics.end_date }}
                        </p>
                    </div>
                    <span class="text-xs text-muted-foreground">Page views per hari</span>
                </div>

                <div class="mt-6 overflow-x-auto pb-2">
                    <div
                        class="flex h-52 min-w-full items-end gap-1"
                        :style="{ width: chartWidth() }"
                    >
                        <div
                            v-for="point in analytics.trend"
                            :key="point.date"
                            class="group relative flex h-full min-w-[6px] flex-1 items-end"
                        >
                            <div
                                class="w-full rounded-t bg-primary/70 transition-colors group-hover:bg-primary"
                                :style="{ height: barHeight(point.page_views) + '%' }"
                            />
                            <div
                                class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg border border-border bg-background px-3 py-2 text-xs shadow-lg group-hover:block"
                            >
                                <p class="font-medium">{{ point.date }}</p>
                                <p class="mt-1 text-muted-foreground">
                                    {{ point.page_views }} view · {{ point.reader_opens }} baca · {{ point.downloads }} download
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
                <section class="overflow-hidden rounded-2xl border border-border bg-surface">
                    <div class="border-b border-border px-5 py-4 sm:px-6">
                        <p class="font-semibold">Ebook dengan engagement tertinggi</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Gabungan detail view, pembukaan reader, dan download pada periode terpilih.
                        </p>
                    </div>

                    <div v-if="analytics.top_books.length" class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-muted/50 text-xs text-muted-foreground">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Ebook</th>
                                    <th class="px-4 py-3 text-right font-medium">Detail</th>
                                    <th class="px-4 py-3 text-right font-medium">Baca</th>
                                    <th class="px-4 py-3 text-right font-medium">Download</th>
                                    <th class="px-5 py-3 text-right font-medium">Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="book in analytics.top_books" :key="book.id">
                                    <td class="px-5 py-4">
                                        <Link
                                            :href="'/book/' + book.slug"
                                            target="_blank"
                                            class="font-medium hover:text-primary"
                                        >
                                            {{ book.title }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-4 text-right">{{ book.detail_views }}</td>
                                    <td class="px-4 py-4 text-right">{{ book.reader_opens }}</td>
                                    <td class="px-4 py-4 text-right">{{ book.downloads }}</td>
                                    <td class="px-5 py-4 text-right font-medium">{{ engagement(book) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="px-6 py-12 text-center text-sm text-muted-foreground">
                        Belum ada engagement ebook pada periode ini.
                    </div>
                </section>

                <div class="grid gap-6">
                    <section class="rounded-2xl border border-border bg-surface p-5">
                        <p class="font-semibold">Kategori populer</p>
                        <div v-if="analytics.top_categories.length" class="mt-4 space-y-3">
                            <div
                                v-for="(item, index) in analytics.top_categories"
                                :key="item.id"
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ index + 1 }}. {{ item.name }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ item.reader_opens }} baca · {{ item.downloads }} download
                                    </p>
                                </div>
                                <span class="text-sm font-semibold">{{ engagement(item) }}</span>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">Belum ada data kategori.</p>
                    </section>

                    <section class="rounded-2xl border border-border bg-surface p-5">
                        <p class="font-semibold">Koleksi populer</p>
                        <div v-if="analytics.top_collections.length" class="mt-4 space-y-3">
                            <div
                                v-for="(item, index) in analytics.top_collections"
                                :key="item.id"
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ index + 1 }}. {{ item.name }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ item.reader_opens }} baca · {{ item.downloads }} download
                                    </p>
                                </div>
                                <span class="text-sm font-semibold">{{ engagement(item) }}</span>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">Belum ada data koleksi.</p>
                    </section>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
