<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { BookOpen, Cloud, Download, Eye, FilePenLine, HardDrive, Plus, Search, Trash2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import type { SharedPageProps } from '@/types';

interface OptionItem {
    value: number;
    label: string;
    active?: boolean;
}

interface EbookItem {
    id: number;
    title: string;
    subtitle: string | null;
    slug: string;
    isbn: string | null;
    cover_url: string | null;
    authors: string[];
    publisher: string | null;
    language: string | null;
    collection: string | null;
    publication_status: 'draft' | 'published' | 'archived';
    read_enabled: boolean;
    download_enabled: boolean;
    file_source_type: 'local' | 'external_url' | null;
    file_verification_status: string | null;
    file_processing_status: string | null;
    file_page_count: number | null;
    file_size_bytes: number | null;
    published_at: string | null;
    updated_at: string | null;
}

interface Paginator {
    data: EbookItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    ebooks: Paginator;
    filters: {
        q: string;
        status: string;
        access: string;
        category_id: number | null;
        author_id: number | null;
        language_id: number | null;
        per_page: number;
    };
    filterOptions: {
        categories: OptionItem[];
        authors: OptionItem[];
        languages: OptionItem[];
    };
}>();

const page = usePage<SharedPageProps>();
const selectedIds = ref<number[]>([]);
const q = ref(props.filters.q);
const status = ref(props.filters.status);
const access = ref(props.filters.access);
const categoryId = ref<number | ''>(props.filters.category_id ?? '');
const authorId = ref<number | ''>(props.filters.author_id ?? '');
const languageId = ref<number | ''>(props.filters.language_id ?? '');
const perPage = ref(props.filters.per_page);

const bulkForm = useForm({
    action: '',
    ids: [] as number[],
});

const allCurrentSelected = computed(() =>
    props.ebooks.data.length > 0
    && props.ebooks.data.every((ebook) => selectedIds.value.includes(ebook.id)),
);

function applyFilters() {
    router.get('/admin/ebooks', {
        q: q.value || undefined,
        status: status.value,
        access: access.value,
        category_id: categoryId.value || undefined,
        author_id: authorId.value || undefined,
        language_id: languageId.value || undefined,
        per_page: perPage.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function clearFilters() {
    q.value = '';
    status.value = 'all';
    access.value = 'all';
    categoryId.value = '';
    authorId.value = '';
    languageId.value = '';
    perPage.value = 25;
    applyFilters();
}

function toggleAll() {
    if (allCurrentSelected.value) {
        const current = new Set(props.ebooks.data.map((ebook) => ebook.id));
        selectedIds.value = selectedIds.value.filter((id) => !current.has(id));
        return;
    }

    selectedIds.value = Array.from(
        new Set([...selectedIds.value, ...props.ebooks.data.map((ebook) => ebook.id)]),
    );
}

function applyBulk() {
    if (!bulkForm.action || selectedIds.value.length === 0) return;

    if (bulkForm.action === 'delete' && !window.confirm('Hapus ebook yang dipilih? Data akan masuk soft delete.')) {
        return;
    }

    bulkForm.ids = [...selectedIds.value];
    bulkForm.post('/admin/ebooks/bulk', {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkForm.reset();
        },
    });
}

function remove(ebook: EbookItem) {
    if (!window.confirm(`Hapus ebook "${ebook.title}"?`)) return;

    router.delete(`/admin/ebooks/${ebook.id}`);
}

function statusLabel(value: EbookItem['publication_status']) {
    return {
        draft: 'Draft',
        published: 'Published',
        archived: 'Archived',
    }[value];
}

function statusClass(value: EbookItem['publication_status']) {
    return {
        draft: 'bg-amber-50 text-amber-700',
        published: 'bg-emerald-50 text-emerald-700',
        archived: 'bg-slate-100 text-slate-600',
    }[value];
}

function formatDate(value: string | null) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatBytes(value: number | null) {
    if (value === null) return '';

    const mb = value / (1024 * 1024);

    if (mb >= 1) {
        return `${mb.toFixed(mb >= 10 ? 1 : 2)} MB`;
    }

    return `${(value / 1024).toFixed(1)} KB`;
}

function processingLabel(value: string | null) {
    return {
        pending: 'Processing pending',
        processing: 'Processing...',
        processed: 'Processed',
        failed: 'Processing gagal',
    }[value ?? ''] ?? value ?? '';
}

function processingClass(value: string | null) {
    return {
        pending: 'text-amber-700',
        processing: 'text-blue-700',
        processed: 'text-emerald-700',
        failed: 'text-red-700',
    }[value ?? ''] ?? 'text-muted-foreground';
}
</script>

<template>
    <Head title="Ebook" />

    <AdminLayout>
        <div class="max-w-[1500px]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <BookOpen class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-primary">Katalog</p>
                        <h1 class="mt-1 text-3xl font-semibold tracking-tight">Ebook</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola metadata, cover, klasifikasi, akses baca/download, dan status publikasi ebook.
                        </p>
                    </div>
                </div>

                <Link href="/admin/ebooks/create">
                    <Button size="large">
                        <Plus class="size-4" />
                        Tambah ebook
                    </Button>
                </Link>
            </div>

            <div v-if="page.props.flash.status" class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.status }}
            </div>

            <section class="mt-7 rounded-2xl border border-border bg-surface">
                <div class="border-b border-border p-4 sm:p-5">
                    <form class="grid gap-3 xl:grid-cols-[minmax(260px,1fr)_repeat(5,minmax(140px,auto))]" @submit.prevent="applyFilters">
                        <div class="flex min-h-11 min-w-0 items-center gap-2 rounded-xl border border-border bg-background px-3">
                            <Search class="size-4 shrink-0 text-muted-foreground" />
                            <input
                                v-model="q"
                                type="search"
                                class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                                placeholder="Judul, ISBN, atau penulis..."
                            >
                        </div>

                        <select v-model="status" class="min-h-11 rounded-xl border border-border bg-background px-3 text-sm">
                            <option value="all">Semua status</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                        </select>

                        <select v-model="access" class="min-h-11 rounded-xl border border-border bg-background px-3 text-sm">
                            <option value="all">Semua akses</option>
                            <option value="readable">Bisa dibaca</option>
                            <option value="downloadable">Bisa diunduh</option>
                            <option value="locked">Baca & download off</option>
                        </select>

                        <select v-model="categoryId" class="min-h-11 rounded-xl border border-border bg-background px-3 text-sm">
                            <option value="">Semua kategori</option>
                            <option v-for="item in filterOptions.categories" :key="item.value" :value="item.value">
                                {{ item.label }}
                            </option>
                        </select>

                        <select v-model="authorId" class="min-h-11 rounded-xl border border-border bg-background px-3 text-sm">
                            <option value="">Semua penulis</option>
                            <option v-for="item in filterOptions.authors" :key="item.value" :value="item.value">
                                {{ item.label }}
                            </option>
                        </select>

                        <select v-model="languageId" class="min-h-11 rounded-xl border border-border bg-background px-3 text-sm">
                            <option value="">Semua bahasa</option>
                            <option v-for="item in filterOptions.languages" :key="item.value" :value="item.value">
                                {{ item.label }}
                            </option>
                        </select>

                        <div class="flex gap-2 xl:col-span-full">
                            <select
                                v-model="perPage"
                                class="min-h-10 rounded-xl border border-border bg-background px-3 text-sm"
                                @change="applyFilters"
                            >
                                <option :value="10">10 / halaman</option>
                                <option :value="25">25 / halaman</option>
                                <option :value="50">50 / halaman</option>
                                <option :value="100">100 / halaman</option>
                            </select>
                            <Button type="submit" variant="secondary">Terapkan filter</Button>
                            <button type="button" class="px-3 text-sm text-muted-foreground hover:text-foreground" @click="clearFilters">
                                Reset
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row sm:items-center">
                        <select
                            v-model="bulkForm.action"
                            class="min-h-10 rounded-xl border border-border bg-background px-3 text-sm"
                        >
                            <option value="">Bulk action...</option>
                            <option value="publish">Publish</option>
                            <option value="draft">Jadikan draft</option>
                            <option value="archive">Archive</option>
                            <option value="enable_read">Aktifkan baca</option>
                            <option value="disable_read">Nonaktifkan baca</option>
                            <option value="enable_download">Aktifkan download</option>
                            <option value="disable_download">Nonaktifkan download</option>
                            <option value="delete">Hapus</option>
                        </select>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="!bulkForm.action || selectedIds.length === 0 || bulkForm.processing"
                            @click="applyBulk"
                        >
                            Terapkan ke {{ selectedIds.length }} ebook
                        </Button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-border bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="w-12 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="allCurrentSelected"
                                        class="size-4 rounded border-border"
                                        aria-label="Pilih semua ebook"
                                        @change="toggleAll"
                                    >
                                </th>
                                <th class="min-w-[340px] px-4 py-3">Ebook</th>
                                <th class="min-w-[180px] px-4 py-3">Klasifikasi</th>
                                <th class="min-w-[140px] px-4 py-3">File PDF</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Akses</th>
                                <th class="whitespace-nowrap px-4 py-3">Diperbarui</th>
                                <th class="w-28 px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="ebook in ebooks.data" :key="ebook.id" class="align-top hover:bg-muted/30">
                                <td class="px-4 py-4">
                                    <input
                                        v-model="selectedIds"
                                        type="checkbox"
                                        :value="ebook.id"
                                        class="size-4 rounded border-border"
                                        :aria-label="`Pilih ${ebook.title}`"
                                    >
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex gap-4">
                                        <div class="flex h-24 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted">
                                            <img v-if="ebook.cover_url" :src="ebook.cover_url" :alt="ebook.title" class="size-full object-cover">
                                            <BookOpen v-else class="size-5 text-muted-foreground" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-semibold leading-6">{{ ebook.title }}</p>
                                            <p v-if="ebook.subtitle" class="mt-0.5 line-clamp-1 text-sm text-muted-foreground">
                                                {{ ebook.subtitle }}
                                            </p>
                                            <p class="mt-2 line-clamp-2 text-xs leading-5 text-muted-foreground">
                                                {{ ebook.authors.length ? ebook.authors.join(', ') : 'Penulis belum ditentukan' }}
                                            </p>
                                            <p v-if="ebook.isbn" class="mt-1 text-xs text-muted-foreground">ISBN {{ ebook.isbn }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 text-sm">
                                    <p>{{ ebook.publisher || '—' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ ebook.language || 'Bahasa belum dipilih' }}
                                    </p>
                                    <p v-if="ebook.collection" class="mt-1 text-xs text-muted-foreground">
                                        {{ ebook.collection }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <div v-if="ebook.file_source_type" class="text-xs">
                                        <span class="inline-flex items-center gap-1.5 font-medium text-foreground">
                                            <HardDrive v-if="ebook.file_source_type === 'local'" class="size-3.5 text-primary" />
                                            <Cloud v-else class="size-3.5 text-primary" />
                                            {{ ebook.file_source_type === 'local' ? 'Local' : 'External' }}
                                        </span>
                                        <p class="mt-1 text-muted-foreground">
                                            {{ ebook.file_size_bytes ? formatBytes(ebook.file_size_bytes) : 'Ukuran belum diketahui' }}
                                        </p>
                                        <p
                                            v-if="ebook.file_verification_status"
                                            class="mt-1"
                                            :class="ebook.file_verification_status === 'verified' ? 'text-emerald-700' : 'text-amber-700'"
                                        >
                                            {{ ebook.file_verification_status === 'verified' ? 'Terverifikasi' : ebook.file_verification_status }}
                                        </p>
                                        <p
                                            v-if="ebook.file_processing_status"
                                            class="mt-1"
                                            :class="processingClass(ebook.file_processing_status)"
                                        >
                                            {{ processingLabel(ebook.file_processing_status) }}
                                            <span v-if="ebook.file_page_count"> · {{ ebook.file_page_count }} hlm</span>
                                        </p>
                                    </div>
                                    <span v-else class="text-xs text-amber-700">Belum ada PDF</span>
                                </td>

                                <td class="px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="statusClass(ebook.publication_status)"
                                    >
                                        {{ statusLabel(ebook.publication_status) }}
                                    </span>
                                    <p v-if="ebook.published_at" class="mt-2 whitespace-nowrap text-xs text-muted-foreground">
                                        {{ formatDate(ebook.published_at) }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex flex-col gap-2 text-xs">
                                        <span class="inline-flex items-center gap-1.5" :class="ebook.read_enabled ? 'text-emerald-700' : 'text-muted-foreground'">
                                            <Eye class="size-3.5" />
                                            {{ ebook.read_enabled ? 'Baca aktif' : 'Baca off' }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5" :class="ebook.download_enabled ? 'text-emerald-700' : 'text-muted-foreground'">
                                            <Download class="size-3.5" />
                                            {{ ebook.download_enabled ? 'Download aktif' : 'Download off' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-4 py-4 text-xs text-muted-foreground">
                                    {{ formatDate(ebook.updated_at) }}
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-1">
                                        <Link
                                            :href="`/admin/ebooks/${ebook.id}/edit`"
                                            class="rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                                            title="Edit"
                                        >
                                            <FilePenLine class="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="rounded-lg p-2 text-muted-foreground hover:bg-red-50 hover:text-red-600"
                                            title="Hapus"
                                            @click="remove(ebook)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!ebooks.data.length">
                                <td colspan="8" class="px-4 py-16 text-center">
                                    <BookOpen class="mx-auto size-8 text-muted-foreground" />
                                    <p class="mt-3 font-medium">Belum ada ebook</p>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        Tambahkan ebook pertama, lalu hubungkan PDF melalui upload lokal atau URL cloud.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-border px-4 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-muted-foreground">
                        Menampilkan {{ ebooks.from || 0 }}–{{ ebooks.to || 0 }} dari {{ ebooks.total }}
                    </span>
                    <div class="flex gap-2">
                        <Link
                            v-if="ebooks.prev_page_url"
                            :href="ebooks.prev_page_url"
                            preserve-scroll
                            class="rounded-lg border border-border px-3 py-2 hover:bg-muted"
                        >
                            Sebelumnya
                        </Link>
                        <span class="rounded-lg bg-muted px-3 py-2">{{ ebooks.current_page }} / {{ ebooks.last_page }}</span>
                        <Link
                            v-if="ebooks.next_page_url"
                            :href="ebooks.next_page_url"
                            preserve-scroll
                            class="rounded-lg border border-border px-3 py-2 hover:bg-muted"
                        >
                            Berikutnya
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
