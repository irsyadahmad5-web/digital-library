<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Cloud,
    Download,
    Eye,
    FilePenLine,
    Filter,
    HardDrive,
    Plus,
    RotateCcw,
    Search,
    Trash2,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
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
const mobileFiltersOpen = ref(false);
const pendingDelete = ref<EbookItem | null>(null);
const deleteBusy = ref(false);
const bulkDeleteConfirmOpen = ref(false);

const bulkForm = useForm({
    action: '',
    ids: [] as number[],
});

const allCurrentSelected = computed(() =>
    props.ebooks.data.length > 0
    && props.ebooks.data.every((ebook) => selectedIds.value.includes(ebook.id)),
);

const advancedFilterCount = computed(() => [
    status.value !== 'all' ? status.value : '',
    access.value !== 'all' ? access.value : '',
    categoryId.value,
    authorId.value,
    languageId.value,
].filter(Boolean).length);

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

    mobileFiltersOpen.value = false;
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

function submitBulk() {
    if (!bulkForm.action || selectedIds.value.length === 0) return;

    bulkForm.ids = [...selectedIds.value];
    bulkForm.post('/admin/ebooks/bulk', {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkForm.reset();
            bulkDeleteConfirmOpen.value = false;
        },
    });
}

function applyBulk() {
    if (!bulkForm.action || selectedIds.value.length === 0) return;

    if (bulkForm.action === 'delete') {
        bulkDeleteConfirmOpen.value = true;
        return;
    }

    submitBulk();
}

function requestRemove(ebook: EbookItem) {
    pendingDelete.value = ebook;
}

function confirmRemove() {
    const ebook = pendingDelete.value;
    if (!ebook || deleteBusy.value) return;

    deleteBusy.value = true;

    router.delete('/admin/ebooks/' + ebook.id, {
        preserveScroll: true,
        onFinish: () => {
            deleteBusy.value = false;
            pendingDelete.value = null;
        },
    });
}

function statusLabel(value: EbookItem['publication_status']) {
    return {
        draft: 'Draft',
        published: 'Published',
        archived: 'Archived',
    }[value];
}

function statusTone(value: EbookItem['publication_status']): 'neutral' | 'success' | 'warning' {
    return {
        draft: 'warning',
        published: 'success',
        archived: 'neutral',
    }[value] as 'neutral' | 'success' | 'warning';
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
        return mb.toFixed(mb >= 10 ? 1 : 2) + ' MB';
    }

    return (value / 1024).toFixed(1) + ' KB';
}

function processingLabel(value: string | null) {
    return {
        pending: 'Menunggu',
        processing: 'Memproses',
        processed: 'Selesai',
        failed: 'Gagal',
    }[value ?? ''] ?? value ?? '';
}

function processingTone(value: string | null): 'neutral' | 'brand' | 'success' | 'warning' | 'danger' {
    return {
        pending: 'warning',
        processing: 'brand',
        processed: 'success',
        failed: 'danger',
    }[value ?? ''] as 'neutral' | 'brand' | 'success' | 'warning' | 'danger' ?? 'neutral';
}
</script>

<template>
    <Head title="Ebook" />

    <AdminLayout>
        <div class="grid gap-5">
            <PageHeader
                eyebrow="Library"
                title="Ebook"
                :description="'Kelola metadata, file PDF, klasifikasi, akses, dan publikasi. ' + ebooks.total + ' ebook tersimpan.'"
            >
                <template #actions>
                    <Button as-child size="small">
                        <Link href="/admin/ebooks/create">
                            <Plus class="size-4" />
                            Tambah ebook
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <Alert v-if="page.props.flash.status" tone="success" :title="page.props.flash.status" />

            <section class="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface">
                <div class="border-b border-line p-3.5 sm:p-4">
                    <form class="grid gap-3" @submit.prevent="applyFilters">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div class="ui-control ui-focus-ring flex min-w-0 flex-1 items-center px-3">
                                <Search class="size-4 shrink-0 text-ink-faint" />
                                <input
                                    v-model="q"
                                    type="search"
                                    class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none placeholder:text-ink-faint"
                                    placeholder="Cari judul, ISBN, atau penulis…"
                                >
                            </div>

                            <Button
                                type="button"
                                variant="secondary"
                                class="lg:hidden"
                                @click="mobileFiltersOpen = !mobileFiltersOpen"
                            >
                                <Filter class="size-4" />
                                Filter
                                <span v-if="advancedFilterCount" class="rounded-full bg-brand px-1.5 py-0.5 text-[10px] text-brand-foreground">
                                    {{ advancedFilterCount }}
                                </span>
                            </Button>

                            <Button type="submit" variant="secondary">Cari</Button>
                        </div>

                        <div
                            class="grid gap-2 sm:grid-cols-2 lg:grid lg:grid-cols-5"
                            :class="mobileFiltersOpen ? 'grid' : 'hidden'"
                        >
                            <Select v-model="status">
                                <option value="all">Semua status</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </Select>

                            <Select v-model="access">
                                <option value="all">Semua akses</option>
                                <option value="readable">Bisa dibaca</option>
                                <option value="downloadable">Bisa diunduh</option>
                                <option value="locked">Baca & download off</option>
                            </Select>

                            <Select v-model="categoryId">
                                <option value="">Semua kategori</option>
                                <option v-for="item in filterOptions.categories" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </Select>

                            <Select v-model="authorId">
                                <option value="">Semua penulis</option>
                                <option v-for="item in filterOptions.authors" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </Select>

                            <Select v-model="languageId">
                                <option value="">Semua bahasa</option>
                                <option v-for="item in filterOptions.languages" :key="item.value" :value="item.value">
                                    {{ item.label }}
                                </option>
                            </Select>

                            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-5">
                                <Select v-model="perPage" class="w-auto min-w-32" @change="applyFilters">
                                    <option :value="10">10 / halaman</option>
                                    <option :value="25">25 / halaman</option>
                                    <option :value="50">50 / halaman</option>
                                    <option :value="100">100 / halaman</option>
                                </Select>
                                <Button type="submit" size="small">Terapkan</Button>
                                <Button type="button" size="small" variant="quiet" @click="clearFilters">
                                    <RotateCcw class="size-3.5" />
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </form>

                    <div
                        v-if="selectedIds.length"
                        class="mt-3 flex flex-col gap-2 border-t border-line pt-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-xs font-semibold text-ink">{{ selectedIds.length }} ebook dipilih</p>
                        <div class="flex flex-wrap gap-2">
                            <Select v-model="bulkForm.action" class="w-auto min-w-44">
                                <option value="">Bulk action…</option>
                                <option value="publish">Publish</option>
                                <option value="draft">Jadikan draft</option>
                                <option value="archive">Archive</option>
                                <option value="enable_read">Aktifkan baca</option>
                                <option value="disable_read">Nonaktifkan baca</option>
                                <option value="enable_download">Aktifkan download</option>
                                <option value="disable_download">Nonaktifkan download</option>
                                <option value="delete">Hapus</option>
                            </Select>
                            <Button
                                type="button"
                                size="small"
                                variant="secondary"
                                :disabled="!bulkForm.action || bulkForm.processing"
                                @click="applyBulk"
                            >
                                Terapkan
                            </Button>
                        </div>
                    </div>
                </div>

                <div v-if="ebooks.data.length" class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-line bg-surface-subtle text-[10px] uppercase tracking-[0.06em] text-ink-faint">
                            <tr>
                                <th class="w-10 px-3 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="allCurrentSelected"
                                        class="size-4 rounded border-line"
                                        aria-label="Pilih semua ebook"
                                        @change="toggleAll"
                                    >
                                </th>
                                <th class="min-w-[300px] px-3 py-3 font-semibold">Ebook</th>
                                <th class="min-w-[150px] px-3 py-3 font-semibold">Klasifikasi</th>
                                <th class="min-w-[135px] px-3 py-3 font-semibold">File PDF</th>
                                <th class="px-3 py-3 font-semibold">Status</th>
                                <th class="px-3 py-3 font-semibold">Akses</th>
                                <th class="whitespace-nowrap px-3 py-3 font-semibold">Diperbarui</th>
                                <th class="w-24 px-3 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="ebook in ebooks.data" :key="ebook.id" class="align-top transition-colors hover:bg-surface-subtle/60">
                                <td class="px-3 py-3">
                                    <input
                                        v-model="selectedIds"
                                        type="checkbox"
                                        :value="ebook.id"
                                        class="size-4 rounded border-line"
                                        :aria-label="'Pilih ' + ebook.title"
                                    >
                                </td>

                                <td class="px-3 py-3">
                                    <div class="flex gap-3">
                                        <div class="flex h-20 w-[54px] shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius-sm)] border border-line bg-surface-subtle">
                                            <img v-if="ebook.cover_url" :src="ebook.cover_url" :alt="ebook.title" class="size-full object-cover">
                                            <BookOpen v-else class="size-4 text-ink-faint" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="line-clamp-2 text-[13px] font-semibold leading-5 text-ink">{{ ebook.title }}</p>
                                            <p v-if="ebook.subtitle" class="mt-0.5 line-clamp-1 text-[11px] text-ink-soft">{{ ebook.subtitle }}</p>
                                            <p class="mt-1.5 line-clamp-2 text-[11px] leading-4 text-ink-faint">
                                                {{ ebook.authors.length ? ebook.authors.join(', ') : 'Penulis belum ditentukan' }}
                                            </p>
                                            <p v-if="ebook.isbn" class="mt-1 text-[10px] text-ink-faint">ISBN {{ ebook.isbn }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-3">
                                    <p class="text-xs font-medium text-ink">{{ ebook.publisher || '—' }}</p>
                                    <p class="mt-1 text-[11px] text-ink-faint">{{ ebook.language || 'Bahasa belum dipilih' }}</p>
                                    <p v-if="ebook.collection" class="mt-1 text-[11px] text-ink-faint">{{ ebook.collection }}</p>
                                </td>

                                <td class="px-3 py-3">
                                    <div v-if="ebook.file_source_type" class="grid gap-1.5">
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-ink">
                                            <HardDrive v-if="ebook.file_source_type === 'local'" class="size-3.5 text-brand" />
                                            <Cloud v-else class="size-3.5 text-brand" />
                                            {{ ebook.file_source_type === 'local' ? 'Local' : 'External' }}
                                        </span>
                                        <span class="text-[11px] text-ink-faint">
                                            {{ ebook.file_size_bytes ? formatBytes(ebook.file_size_bytes) : 'Ukuran belum diketahui' }}
                                        </span>
                                        <Badge v-if="ebook.file_processing_status" :tone="processingTone(ebook.file_processing_status)">
                                            {{ processingLabel(ebook.file_processing_status) }}
                                            <template v-if="ebook.file_page_count"> · {{ ebook.file_page_count }} hlm</template>
                                        </Badge>
                                    </div>
                                    <Badge v-else tone="warning">Belum ada PDF</Badge>
                                </td>

                                <td class="px-3 py-3">
                                    <Badge :tone="statusTone(ebook.publication_status)">
                                        {{ statusLabel(ebook.publication_status) }}
                                    </Badge>
                                    <p v-if="ebook.published_at" class="mt-1.5 whitespace-nowrap text-[10px] text-ink-faint">
                                        {{ formatDate(ebook.published_at) }}
                                    </p>
                                </td>

                                <td class="px-3 py-3">
                                    <div class="grid gap-1.5 text-[11px]">
                                        <span class="inline-flex items-center gap-1.5" :class="ebook.read_enabled ? 'text-success' : 'text-ink-faint'">
                                            <Eye class="size-3.5" />
                                            {{ ebook.read_enabled ? 'Baca aktif' : 'Baca off' }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5" :class="ebook.download_enabled ? 'text-success' : 'text-ink-faint'">
                                            <Download class="size-3.5" />
                                            {{ ebook.download_enabled ? 'Download aktif' : 'Download off' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-3 py-3 text-[11px] text-ink-faint">
                                    {{ formatDate(ebook.updated_at) }}
                                </td>

                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Link
                                            :href="'/admin/ebooks/' + ebook.id + '/edit'"
                                            class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink"
                                            title="Edit"
                                            :aria-label="'Edit ' + ebook.title"
                                        >
                                            <FilePenLine class="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-danger-soft hover:text-danger"
                                            title="Hapus"
                                            :aria-label="'Hapus ' + ebook.title"
                                            @click="requestRemove(ebook)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="ebooks.data.length" class="divide-y divide-line md:hidden">
                    <article v-for="ebook in ebooks.data" :key="ebook.id" class="p-4">
                        <div class="flex gap-3">
                            <input
                                v-model="selectedIds"
                                type="checkbox"
                                :value="ebook.id"
                                class="mt-1 size-4 shrink-0 rounded border-line"
                                :aria-label="'Pilih ' + ebook.title"
                            >
                            <div class="h-[88px] w-[60px] shrink-0 overflow-hidden rounded-[var(--radius-sm)] border border-line bg-surface-subtle">
                                <img v-if="ebook.cover_url" :src="ebook.cover_url" :alt="ebook.title" class="size-full object-cover">
                                <div v-else class="grid size-full place-items-center"><BookOpen class="size-4 text-ink-faint" /></div>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="line-clamp-2 text-sm font-semibold leading-5 text-ink">{{ ebook.title }}</p>
                                        <p class="mt-1 line-clamp-1 text-xs text-ink-soft">
                                            {{ ebook.authors.length ? ebook.authors.join(', ') : 'Penulis belum ditentukan' }}
                                        </p>
                                    </div>
                                    <Badge :tone="statusTone(ebook.publication_status)">{{ statusLabel(ebook.publication_status) }}</Badge>
                                </div>

                                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-ink-faint">
                                    <span v-if="ebook.language">{{ ebook.language }}</span>
                                    <span v-if="ebook.file_page_count">{{ ebook.file_page_count }} hlm</span>
                                    <span v-if="ebook.file_size_bytes">{{ formatBytes(ebook.file_size_bytes) }}</span>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <div class="flex gap-2 text-[11px]">
                                        <span :class="ebook.read_enabled ? 'text-success' : 'text-ink-faint'">Baca {{ ebook.read_enabled ? 'on' : 'off' }}</span>
                                        <span :class="ebook.download_enabled ? 'text-success' : 'text-ink-faint'">Download {{ ebook.download_enabled ? 'on' : 'off' }}</span>
                                    </div>

                                    <div class="flex gap-1">
                                        <Link
                                            :href="'/admin/ebooks/' + ebook.id + '/edit'"
                                            class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink"
                                            :aria-label="'Edit ' + ebook.title"
                                        >
                                            <FilePenLine class="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-danger-soft hover:text-danger"
                                            :aria-label="'Hapus ' + ebook.title"
                                            @click="requestRemove(ebook)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <EmptyState
                    v-if="!ebooks.data.length"
                    title="Belum ada ebook"
                    description="Tambahkan ebook pertama, lalu hubungkan PDF melalui upload lokal atau URL cloud."
                >
                    <template #icon><BookOpen class="size-5" /></template>
                    <template #actions>
                        <Button as-child size="small">
                            <Link href="/admin/ebooks/create">
                                <Plus class="size-4" />
                                Tambah ebook
                            </Link>
                        </Button>
                    </template>
                </EmptyState>

                <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-ink-soft">
                        {{ ebooks.from || 0 }}–{{ ebooks.to || 0 }} dari {{ ebooks.total }}
                    </span>
                    <div v-if="ebooks.last_page > 1" class="flex items-center gap-1.5">
                        <Button v-if="ebooks.prev_page_url" as-child size="small" variant="secondary">
                            <Link :href="ebooks.prev_page_url" preserve-scroll>Sebelumnya</Link>
                        </Button>
                        <span class="min-w-16 px-2 text-center font-semibold tabular-nums text-ink-soft">
                            {{ ebooks.current_page }} / {{ ebooks.last_page }}
                        </span>
                        <Button v-if="ebooks.next_page_url" as-child size="small" variant="secondary">
                            <Link :href="ebooks.next_page_url" preserve-scroll>Berikutnya</Link>
                        </Button>
                    </div>
                </div>
            </section>
        </div>

        <ConfirmDialog
            :open="Boolean(pendingDelete)"
            title="Hapus ebook?"
            :description="pendingDelete ? 'Ebook “' + pendingDelete.title + '” akan masuk soft delete.' : ''"
            confirm-label="Hapus ebook"
            destructive
            :busy="deleteBusy"
            @update:open="pendingDelete = $event ? pendingDelete : null"
            @confirm="confirmRemove"
        />

        <ConfirmDialog
            :open="bulkDeleteConfirmOpen"
            title="Hapus ebook terpilih?"
            :description="selectedIds.length + ' ebook akan masuk soft delete. Tindakan ini tidak menghapus file secara langsung.'"
            confirm-label="Hapus yang dipilih"
            destructive
            :busy="bulkForm.processing"
            @update:open="bulkDeleteConfirmOpen = $event"
            @confirm="submitBulk"
        />
    </AdminLayout>
</template>
