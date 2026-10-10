<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    CheckCircle2,
    FileImage,
    Save,
    Upload,
    XCircle,
} from '@lucide/vue';
import EbookStoragePanel from '@/components/admin/EbookStoragePanel.vue';
import RelationChecklist from '@/components/admin/RelationChecklist.vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { SharedPageProps } from '@/types';

interface OptionItem {
    value: number;
    label: string;
    active?: boolean;
}

interface FileSource {
    id: number;
    source_type: 'local' | 'external_url';
    original_name: string | null;
    external_url: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    sha256: string | null;
    verification_status: string;
    processing_status: string;
    page_count: number | null;
    pdf_metadata: Record<string, unknown> | null;
    preview_url: string | null;
    processed_at: string | null;
    processing_error: string | null;
    verified_at: string | null;
    last_checked_at: string | null;
    last_error: string | null;
}

interface ProcessingConfig {
    pdfinfo_available: boolean;
    pdftocairo_available: boolean;
    available: boolean;
}

interface UploadConfig {
    max_pdf_mb: number;
    max_pdf_bytes: number;
    configured_chunk_mb: number;
    effective_chunk_bytes: number;
    checksum_enabled: boolean;
    preferred_source: 'local' | 'external_url';
    verify_external_urls: boolean;
    https_only_external: boolean;
}

interface EbookData {
    id: number;
    title: string;
    subtitle: string | null;
    slug: string;
    isbn: string | null;
    description: string | null;
    publication_year: number | null;
    edition: string | null;
    page_count: number | null;
    cover_url: string | null;
    publisher_id: number | null;
    language_id: number | null;
    collection_id: number | null;
    publication_status: 'draft' | 'published' | 'archived';
    read_enabled: boolean;
    download_enabled: boolean;
    published_at: string | null;
    authors: number[];
    categories: number[];
    tags: number[];
}

const props = defineProps<{
    ebook: EbookData | null;
    maxCoverMb: number;
    fileSource: FileSource | null;
    processingConfig: ProcessingConfig;
    uploadConfig: UploadConfig;
    options: {
        authors: OptionItem[];
        categories: OptionItem[];
        publishers: OptionItem[];
        languages: OptionItem[];
        collections: OptionItem[];
        tags: OptionItem[];
    };
}>();

const page = usePage<SharedPageProps>();
const isEdit = computed(() => props.ebook !== null);
const localPreview = ref<string | null>(null);

const form = useForm({
    _method: isEdit.value ? 'put' : 'post',
    title: props.ebook?.title ?? '',
    subtitle: props.ebook?.subtitle ?? '',
    slug: props.ebook?.slug ?? '',
    isbn: props.ebook?.isbn ?? '',
    description: props.ebook?.description ?? '',
    publication_year: props.ebook?.publication_year ?? null as number | null,
    edition: props.ebook?.edition ?? '',
    page_count: props.ebook?.page_count ?? null as number | null,
    publisher_id: props.ebook?.publisher_id ?? null as number | null,
    language_id: props.ebook?.language_id ?? null as number | null,
    collection_id: props.ebook?.collection_id ?? null as number | null,
    publication_status: props.ebook?.publication_status ?? 'draft',
    read_enabled: props.ebook?.read_enabled ?? true,
    download_enabled: props.ebook?.download_enabled ?? true,
    authors: props.ebook?.authors ?? [] as number[],
    categories: props.ebook?.categories ?? [] as number[],
    tags: props.ebook?.tags ?? [] as number[],
    cover: null as File | null,
    remove_cover: false,
});

const coverPreview = computed(() => {
    if (form.remove_cover) return null;

    return localPreview.value || props.ebook?.cover_url || null;
});

function onCoverChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
        localPreview.value = null;
    }

    form.cover = file;
    form.remove_cover = false;

    if (file) {
        localPreview.value = URL.createObjectURL(file);
    }
}

function removeCover() {
    form.cover = null;
    form.remove_cover = true;

    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
        localPreview.value = null;
    }
}

function submit() {
    const endpoint = isEdit.value
        ? '/admin/ebooks/' + props.ebook?.id
        : '/admin/ebooks';

    form.post(endpoint, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.cover = null;
            form.remove_cover = false;

            if (localPreview.value) {
                URL.revokeObjectURL(localPreview.value);
                localPreview.value = null;
            }
        },
    });
}

function publicationStatusHelp() {
    if (form.publication_status === 'published') {
        return 'Published tampil sebagai kandidat koleksi publik setelah file dan syarat publikasi terpenuhi.';
    }

    if (form.publication_status === 'archived') {
        return 'Archived disimpan untuk riwayat tetapi tidak ditampilkan sebagai koleksi aktif.';
    }

    return 'Draft hanya tersedia di admin dan belum dianggap terbit.';
}

function publishedAtLabel() {
    if (!props.ebook?.published_at) return '';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(props.ebook.published_at));
}

onBeforeUnmount(() => {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
    }
});
</script>

<template>
    <Head :title="isEdit ? 'Edit Ebook — ' + ebook?.title : 'Tambah Ebook'" />

    <AdminLayout>
        <div class="grid gap-5">
            <div>
                <Link
                    href="/admin/ebooks"
                    class="mb-4 inline-flex min-h-9 items-center gap-1.5 rounded-[var(--radius-md)] px-1 text-xs font-semibold text-ink-soft hover:text-ink"
                >
                    <ArrowLeft class="size-4" />
                    Kembali ke Ebook
                </Link>

                <PageHeader
                    eyebrow="Library"
                    :title="isEdit ? 'Edit Ebook' : 'Tambah Ebook'"
                    description="Kelola metadata, source PDF, klasifikasi, cover, serta kebijakan akses dari satu workspace."
                >
                    <template #actions>
                        <span
                            v-if="isEdit && ebook?.published_at"
                            class="rounded-full bg-success-soft px-3 py-1.5 text-[11px] font-semibold text-success"
                        >
                            Pernah dipublish · {{ publishedAtLabel() }}
                        </span>
                    </template>
                </PageHeader>
            </div>

            <Alert v-if="page.props.flash.status" tone="success" :title="page.props.flash.status" />

            <form class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="submit">
                <div class="grid gap-5">
                    <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
                        <div class="border-b border-line px-4 py-3.5 sm:px-5">
                            <h2 class="text-sm font-semibold text-ink">Metadata utama</h2>
                            <p class="mt-1 text-xs text-ink-soft">Informasi bibliografi dasar ebook.</p>
                        </div>

                        <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-2">
                            <label class="grid gap-1.5 lg:col-span-2">
                                <span class="text-xs font-semibold text-ink">Judul <span class="text-danger">*</span></span>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    class="ui-control ui-focus-ring w-full px-3 text-sm"
                                    placeholder="Judul ebook"
                                >
                                <p v-if="form.errors.title" class="text-xs text-danger">{{ form.errors.title }}</p>
                            </label>

                            <label class="grid gap-1.5 lg:col-span-2">
                                <span class="text-xs font-semibold text-ink">Subjudul</span>
                                <input
                                    v-model="form.subtitle"
                                    type="text"
                                    class="ui-control ui-focus-ring w-full px-3 text-sm"
                                    placeholder="Opsional"
                                >
                                <p v-if="form.errors.subtitle" class="text-xs text-danger">{{ form.errors.subtitle }}</p>
                            </label>

                            <label class="grid gap-1.5">
                                <span class="text-xs font-semibold text-ink">Slug</span>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    class="ui-control ui-focus-ring w-full px-3 text-sm"
                                    placeholder="otomatis-dari-judul"
                                >
                                <p class="text-[11px] leading-5 text-ink-faint">Kosongkan saat membuat ebook agar dibuat otomatis.</p>
                                <p v-if="form.errors.slug" class="text-xs text-danger">{{ form.errors.slug }}</p>
                            </label>

                            <label class="grid gap-1.5">
                                <span class="text-xs font-semibold text-ink">ISBN</span>
                                <input
                                    v-model="form.isbn"
                                    type="text"
                                    class="ui-control ui-focus-ring w-full px-3 text-sm"
                                    placeholder="978602…"
                                >
                                <p class="text-[11px] leading-5 text-ink-faint">ISBN-10 atau ISBN-13; spasi dan tanda hubung dinormalisasi otomatis.</p>
                                <p v-if="form.errors.isbn" class="text-xs text-danger">{{ form.errors.isbn }}</p>
                            </label>

                            <label class="grid gap-1.5">
                                <span class="text-xs font-semibold text-ink">Tahun terbit</span>
                                <input
                                    v-model.number="form.publication_year"
                                    type="number"
                                    min="1000"
                                    :max="new Date().getFullYear() + 1"
                                    class="ui-control ui-focus-ring w-full px-3 text-sm"
                                >
                                <p v-if="form.errors.publication_year" class="text-xs text-danger">{{ form.errors.publication_year }}</p>
                            </label>

                            <label class="grid gap-1.5">
                                <span class="text-xs font-semibold text-ink">Edisi</span>
                                <input
                                    v-model="form.edition"
                                    type="text"
                                    class="ui-control ui-focus-ring w-full px-3 text-sm"
                                    placeholder="Contoh: Edisi 2"
                                >
                                <p v-if="form.errors.edition" class="text-xs text-danger">{{ form.errors.edition }}</p>
                            </label>

                            <label class="grid gap-1.5">
                                <span class="text-xs font-semibold text-ink">Jumlah halaman</span>
                                <input
                                    v-model.number="form.page_count"
                                    type="number"
                                    min="1"
                                    max="100000"
                                    class="ui-control ui-focus-ring w-full px-3 text-sm"
                                >
                                <p class="text-[11px] leading-5 text-ink-faint">Opsional. PDF Processing dapat mengisi otomatis.</p>
                                <p v-if="form.errors.page_count" class="text-xs text-danger">{{ form.errors.page_count }}</p>
                            </label>

                            <label class="grid gap-1.5 lg:col-span-2">
                                <span class="text-xs font-semibold text-ink">Deskripsi</span>
                                <textarea
                                    v-model="form.description"
                                    rows="7"
                                    class="ui-control ui-focus-ring w-full px-3 py-2.5 text-sm leading-6"
                                    placeholder="Sinopsis atau deskripsi ebook…"
                                />
                                <p v-if="form.errors.description" class="text-xs text-danger">{{ form.errors.description }}</p>
                            </label>
                        </div>
                    </section>

                    <EbookStoragePanel
                        v-if="ebook"
                        :ebook-id="ebook.id"
                        :source="fileSource"
                        :config="uploadConfig"
                        :processing-config="processingConfig"
                    />

                    <Alert
                        v-else
                        tone="info"
                        title="File PDF tersedia setelah metadata disimpan"
                    >
                        Simpan ebook terlebih dahulu. Setelah ID terbentuk, halaman edit akan menyediakan upload chunk/resumable dan URL cloud.
                    </Alert>

                    <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
                        <div class="border-b border-line px-4 py-3.5 sm:px-5">
                            <h2 class="text-sm font-semibold text-ink">Klasifikasi</h2>
                            <p class="mt-1 text-xs text-ink-soft">Hubungkan ebook dengan master data agar katalog dan pencarian tetap konsisten.</p>
                        </div>

                        <div class="grid gap-5 p-4 sm:p-5 lg:grid-cols-2">
                            <RelationChecklist
                                v-model="form.authors"
                                label="Penulis"
                                :options="options.authors"
                                description="Urutan yang dipilih dipertahankan sebagai urutan penulis."
                                search-placeholder="Cari penulis…"
                            />

                            <RelationChecklist
                                v-model="form.categories"
                                label="Kategori & subkategori"
                                :options="options.categories"
                                description="Ebook dapat berada pada lebih dari satu kategori."
                                search-placeholder="Cari kategori…"
                            />

                            <RelationChecklist
                                v-model="form.tags"
                                label="Tag"
                                :options="options.tags"
                                description="Gunakan tag untuk topik yang lebih fleksibel daripada kategori."
                                search-placeholder="Cari tag…"
                            />

                            <div class="grid content-start gap-4">
                                <label class="grid gap-1.5">
                                    <span class="text-xs font-semibold text-ink">Penerbit</span>
                                    <Select v-model="form.publisher_id">
                                        <option :value="null">— Belum ditentukan —</option>
                                        <option v-for="item in options.publishers" :key="item.value" :value="item.value">
                                            {{ item.label }}{{ item.active === false ? ' (nonaktif)' : '' }}
                                        </option>
                                    </Select>
                                    <p v-if="form.errors.publisher_id" class="text-xs text-danger">{{ form.errors.publisher_id }}</p>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-xs font-semibold text-ink">Bahasa</span>
                                    <Select v-model="form.language_id">
                                        <option :value="null">— Belum ditentukan —</option>
                                        <option v-for="item in options.languages" :key="item.value" :value="item.value">
                                            {{ item.label }}{{ item.active === false ? ' (nonaktif)' : '' }}
                                        </option>
                                    </Select>
                                    <p v-if="form.errors.language_id" class="text-xs text-danger">{{ form.errors.language_id }}</p>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-xs font-semibold text-ink">Koleksi</span>
                                    <Select v-model="form.collection_id">
                                        <option :value="null">— Belum ditentukan —</option>
                                        <option v-for="item in options.collections" :key="item.value" :value="item.value">
                                            {{ item.label }}{{ item.active === false ? ' (nonaktif)' : '' }}
                                        </option>
                                    </Select>
                                    <p v-if="form.errors.collection_id" class="text-xs text-danger">{{ form.errors.collection_id }}</p>
                                </label>
                            </div>

                            <p v-if="form.errors.authors || form.errors.categories || form.errors.tags" class="text-xs text-danger lg:col-span-2">
                                {{ form.errors.authors || form.errors.categories || form.errors.tags }}
                            </p>
                        </div>
                    </section>
                </div>

                <aside class="grid content-start gap-4 xl:sticky xl:top-20 xl:h-fit">
                    <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
                        <div class="border-b border-line px-4 py-3.5">
                            <h2 class="text-sm font-semibold text-ink">Publikasi & akses</h2>
                        </div>

                        <div class="grid gap-4 p-4">
                            <label class="grid gap-1.5">
                                <span class="text-xs font-semibold text-ink">Status publikasi</span>
                                <Select v-model="form.publication_status">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="archived">Archived</option>
                                </Select>
                                <p class="text-[11px] leading-5 text-ink-faint">{{ publicationStatusHelp() }}</p>
                                <p v-if="form.errors.publication_status" class="text-xs text-danger">{{ form.errors.publication_status }}</p>
                            </label>

                            <div class="flex items-center justify-between gap-4 rounded-[var(--radius-md)] border border-line px-3 py-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-ink">Boleh dibaca</p>
                                    <p class="mt-1 text-[11px] leading-4 text-ink-faint">Kontrol reader publik per ebook.</p>
                                </div>
                                <Switch v-model="form.read_enabled" />
                            </div>

                            <div class="flex items-center justify-between gap-4 rounded-[var(--radius-md)] border border-line px-3 py-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-ink">Boleh diunduh</p>
                                    <p class="mt-1 text-[11px] leading-4 text-ink-faint">Kontrol download publik per ebook.</p>
                                </div>
                                <Switch v-model="form.download_enabled" />
                            </div>

                            <p class="rounded-[var(--radius-md)] bg-surface-subtle px-3 py-2.5 text-[11px] leading-5 text-ink-soft">
                                Akses diterapkan langsung pada route publik yang aman. File private dan URL eksternal tidak diekspos.
                            </p>
                        </div>
                    </section>

                    <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
                        <div class="border-b border-line px-4 py-3.5">
                            <h2 class="text-sm font-semibold text-ink">Cover</h2>
                        </div>

                        <div class="p-4">
                            <div class="mx-auto flex aspect-[3/4] w-full max-w-[190px] items-center justify-center overflow-hidden rounded-[var(--radius-lg)] border border-dashed border-line bg-surface-subtle">
                                <img v-if="coverPreview" :src="String(coverPreview)" alt="" class="size-full object-cover">
                                <div v-else class="text-center text-ink-faint">
                                    <FileImage class="mx-auto size-7" />
                                    <p class="mt-2 text-xs">Belum ada cover</p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap justify-center gap-2">
                                <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-[var(--radius-md)] border border-line bg-surface px-3 text-xs font-semibold text-ink hover:bg-surface-subtle">
                                    <Upload class="size-4" />
                                    Pilih cover
                                    <input
                                        type="file"
                                        class="sr-only"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        @change="onCoverChange"
                                    >
                                </label>
                                <Button
                                    v-if="coverPreview"
                                    type="button"
                                    size="small"
                                    variant="quiet"
                                    class="text-danger hover:bg-danger-soft hover:text-danger"
                                    @click="removeCover"
                                >
                                    Hapus
                                </Button>
                            </div>

                            <p class="mt-3 text-center text-[11px] leading-5 text-ink-faint">
                                JPG, PNG, atau WebP · Maks. {{ maxCoverMb }} MB.
                            </p>
                            <p v-if="form.errors.cover" class="mt-2 text-center text-xs text-danger">{{ form.errors.cover }}</p>
                        </div>
                    </section>

                    <section class="rounded-[var(--radius-lg)] border border-line bg-surface p-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 text-brand">
                                <CheckCircle2 v-if="!form.hasErrors" class="size-4" />
                                <XCircle v-else class="size-4 text-danger" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-ink">
                                    {{ form.hasErrors ? 'Periksa form' : 'Siap disimpan' }}
                                </p>
                                <p class="mt-1 text-[11px] leading-5 text-ink-soft">
                                    Metadata dapat diubah kapan saja tanpa memindahkan source PDF.
                                </p>
                            </div>
                        </div>

                        <Button class="mt-4 w-full" :disabled="form.processing">
                            <Save class="size-4" />
                            {{ form.processing ? 'Menyimpan…' : (isEdit ? 'Simpan perubahan' : 'Simpan ebook') }}
                        </Button>

                        <Button as-child variant="quiet" class="mt-2 w-full">
                            <Link href="/admin/ebooks">Batal</Link>
                        </Button>
                    </section>
                </aside>
            </form>
        </div>
    </AdminLayout>
</template>
