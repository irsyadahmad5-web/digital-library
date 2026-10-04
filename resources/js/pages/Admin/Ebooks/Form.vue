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
import { Button } from '@/components/ui/button';
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
    verified_at: string | null;
    last_checked_at: string | null;
    last_error: string | null;
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
        ? `/admin/ebooks/${props.ebook?.id}`
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
        return 'Published akan menjadi kandidat tampil pada halaman publik saat Public Library selesai.';
    }

    if (form.publication_status === 'archived') {
        return 'Archived disimpan untuk riwayat tetapi tidak ditampilkan sebagai koleksi aktif.';
    }

    return 'Draft hanya tersedia di admin dan belum dianggap terbit.';
}

onBeforeUnmount(() => {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
    }
});
</script>

<template>
    <Head :title="isEdit ? `Edit Ebook — ${ebook?.title}` : 'Tambah Ebook'" />

    <AdminLayout>
        <div class="max-w-7xl">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <Link href="/admin/ebooks" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeft class="size-4" />
                        Kembali ke Ebook
                    </Link>
                    <p class="mt-5 text-sm font-medium text-primary">Katalog</p>
                    <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                        {{ isEdit ? 'Edit Ebook' : 'Tambah Ebook' }}
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Lengkapi metadata, source PDF, klasifikasi, dan kebijakan akses ebook dari satu halaman.
                    </p>
                </div>

                <div v-if="isEdit && ebook?.published_at" class="text-sm text-muted-foreground">
                    Pernah dipublish: {{ new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(ebook.published_at)) }}
                </div>
            </div>

            <div v-if="page.props.flash.status" class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.status }}
            </div>

            <form class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]" @submit.prevent="submit">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-border bg-surface">
                        <div class="border-b border-border px-5 py-5 sm:px-7">
                            <h2 class="text-lg font-semibold">Metadata utama</h2>
                            <p class="mt-1 text-sm text-muted-foreground">Informasi bibliografi dasar ebook.</p>
                        </div>

                        <div class="grid gap-5 p-5 sm:p-7 lg:grid-cols-2">
                            <label class="lg:col-span-2">
                                <span class="mb-2 block text-sm font-medium">Judul <span class="text-red-500">*</span></span>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                    placeholder="Judul ebook"
                                >
                                <p v-if="form.errors.title" class="mt-2 text-sm text-red-600">{{ form.errors.title }}</p>
                            </label>

                            <label class="lg:col-span-2">
                                <span class="mb-2 block text-sm font-medium">Subjudul</span>
                                <input
                                    v-model="form.subtitle"
                                    type="text"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                    placeholder="Opsional"
                                >
                                <p v-if="form.errors.subtitle" class="mt-2 text-sm text-red-600">{{ form.errors.subtitle }}</p>
                            </label>

                            <label>
                                <span class="mb-2 block text-sm font-medium">Slug</span>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                    placeholder="otomatis-dari-judul"
                                >
                                <p class="mt-1.5 text-xs text-muted-foreground">Kosongkan saat membuat ebook agar dibuat otomatis.</p>
                                <p v-if="form.errors.slug" class="mt-2 text-sm text-red-600">{{ form.errors.slug }}</p>
                            </label>

                            <label>
                                <span class="mb-2 block text-sm font-medium">ISBN</span>
                                <input
                                    v-model="form.isbn"
                                    type="text"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                    placeholder="978602..."
                                >
                                <p class="mt-1.5 text-xs text-muted-foreground">ISBN-10 atau ISBN-13; spasi dan tanda hubung dinormalisasi otomatis.</p>
                                <p v-if="form.errors.isbn" class="mt-2 text-sm text-red-600">{{ form.errors.isbn }}</p>
                            </label>

                            <label>
                                <span class="mb-2 block text-sm font-medium">Tahun terbit</span>
                                <input
                                    v-model="form.publication_year"
                                    type="number"
                                    min="1000"
                                    :max="new Date().getFullYear() + 1"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                >
                                <p v-if="form.errors.publication_year" class="mt-2 text-sm text-red-600">{{ form.errors.publication_year }}</p>
                            </label>

                            <label>
                                <span class="mb-2 block text-sm font-medium">Edisi</span>
                                <input
                                    v-model="form.edition"
                                    type="text"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                    placeholder="Contoh: Edisi 2"
                                >
                                <p v-if="form.errors.edition" class="mt-2 text-sm text-red-600">{{ form.errors.edition }}</p>
                            </label>

                            <label>
                                <span class="mb-2 block text-sm font-medium">Jumlah halaman</span>
                                <input
                                    v-model="form.page_count"
                                    type="number"
                                    min="1"
                                    max="100000"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                >
                                <p class="mt-1.5 text-xs text-muted-foreground">Opsional. Stage PDF Processing dapat mengisi otomatis nanti.</p>
                                <p v-if="form.errors.page_count" class="mt-2 text-sm text-red-600">{{ form.errors.page_count }}</p>
                            </label>

                            <label class="lg:col-span-2">
                                <span class="mb-2 block text-sm font-medium">Deskripsi</span>
                                <textarea
                                    v-model="form.description"
                                    rows="8"
                                    class="w-full rounded-xl border border-border bg-background px-4 py-3 text-sm leading-6 outline-none focus:ring-2 focus:ring-primary/20"
                                    placeholder="Sinopsis atau deskripsi ebook..."
                                />
                                <p v-if="form.errors.description" class="mt-2 text-sm text-red-600">{{ form.errors.description }}</p>
                            </label>
                        </div>
                    </section>

                    <EbookStoragePanel
                        v-if="ebook"
                        :ebook-id="ebook.id"
                        :source="fileSource"
                        :config="uploadConfig"
                    />

                    <section v-else class="rounded-2xl border border-border bg-surface">
                        <div class="p-5 sm:p-7">
                            <div class="flex items-start gap-3">
                                <BookOpen class="mt-0.5 size-5 shrink-0 text-primary" />
                                <div>
                                    <h2 class="font-semibold">File PDF tersedia setelah metadata disimpan</h2>
                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                                        Simpan ebook terlebih dahulu. Setelah ID ebook terbentuk, halaman edit akan menampilkan upload chunk/resumable dan pilihan URL cloud.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-border bg-surface">
                        <div class="border-b border-border px-5 py-5 sm:px-7">
                            <h2 class="text-lg font-semibold">Klasifikasi</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Hubungkan ebook dengan master data agar katalog dan pencarian nanti lebih akurat.
                            </p>
                        </div>

                        <div class="grid gap-6 p-5 sm:p-7 lg:grid-cols-2">
                            <RelationChecklist
                                v-model="form.authors"
                                label="Penulis"
                                :options="options.authors"
                                description="Urutan checkbox yang dipilih dipertahankan sebagai urutan penulis."
                                search-placeholder="Cari penulis..."
                            />

                            <RelationChecklist
                                v-model="form.categories"
                                label="Kategori & subkategori"
                                :options="options.categories"
                                description="Ebook dapat berada pada lebih dari satu kategori."
                                search-placeholder="Cari kategori..."
                            />

                            <RelationChecklist
                                v-model="form.tags"
                                label="Tag"
                                :options="options.tags"
                                description="Gunakan tag untuk topik yang lebih fleksibel daripada kategori."
                                search-placeholder="Cari tag..."
                            />

                            <div class="space-y-5">
                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium">Penerbit</span>
                                    <select
                                        v-model="form.publisher_id"
                                        class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none"
                                    >
                                        <option :value="null">— Belum ditentukan —</option>
                                        <option v-for="item in options.publishers" :key="item.value" :value="item.value">
                                            {{ item.label }}{{ item.active === false ? ' (nonaktif)' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.publisher_id" class="mt-2 text-sm text-red-600">{{ form.errors.publisher_id }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium">Bahasa</span>
                                    <select
                                        v-model="form.language_id"
                                        class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none"
                                    >
                                        <option :value="null">— Belum ditentukan —</option>
                                        <option v-for="item in options.languages" :key="item.value" :value="item.value">
                                            {{ item.label }}{{ item.active === false ? ' (nonaktif)' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.language_id" class="mt-2 text-sm text-red-600">{{ form.errors.language_id }}</p>
                                </label>

                                <label class="block">
                                    <span class="mb-2 block text-sm font-medium">Koleksi</span>
                                    <select
                                        v-model="form.collection_id"
                                        class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none"
                                    >
                                        <option :value="null">— Belum ditentukan —</option>
                                        <option v-for="item in options.collections" :key="item.value" :value="item.value">
                                            {{ item.label }}{{ item.active === false ? ' (nonaktif)' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.collection_id" class="mt-2 text-sm text-red-600">{{ form.errors.collection_id }}</p>
                                </label>
                            </div>

                            <p v-if="form.errors.authors || form.errors.categories || form.errors.tags" class="lg:col-span-2 text-sm text-red-600">
                                {{ form.errors.authors || form.errors.categories || form.errors.tags }}
                            </p>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6 xl:sticky xl:top-6 xl:h-fit">
                    <section class="rounded-2xl border border-border bg-surface">
                        <div class="border-b border-border px-5 py-4">
                            <h2 class="font-semibold">Publikasi & akses</h2>
                        </div>

                        <div class="space-y-5 p-5">
                            <label class="block">
                                <span class="mb-2 block text-sm font-medium">Status publikasi</span>
                                <select
                                    v-model="form.publication_status"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none"
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="archived">Archived</option>
                                </select>
                                <p class="mt-2 text-xs leading-5 text-muted-foreground">{{ publicationStatusHelp() }}</p>
                                <p v-if="form.errors.publication_status" class="mt-2 text-sm text-red-600">{{ form.errors.publication_status }}</p>
                            </label>

                            <label class="flex min-h-12 items-center justify-between gap-4 rounded-xl border border-border bg-background px-4">
                                <span>
                                    <span class="block text-sm font-medium">Boleh dibaca</span>
                                    <span class="mt-0.5 block text-xs text-muted-foreground">Kontrol reader publik per ebook.</span>
                                </span>
                                <input v-model="form.read_enabled" type="checkbox" class="size-4 rounded border-border">
                            </label>

                            <label class="flex min-h-12 items-center justify-between gap-4 rounded-xl border border-border bg-background px-4">
                                <span>
                                    <span class="block text-sm font-medium">Boleh diunduh</span>
                                    <span class="mt-0.5 block text-xs text-muted-foreground">Kontrol download publik per ebook.</span>
                                </span>
                                <input v-model="form.download_enabled" type="checkbox" class="size-4 rounded border-border">
                            </label>

                            <div class="rounded-xl bg-muted px-4 py-3 text-xs leading-5 text-muted-foreground">
                                Route baca/download aman akan diimplementasikan pada stage reader dan access control. Flag ini sudah menjadi sumber kebijakan per ebook.
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-border bg-surface">
                        <div class="border-b border-border px-5 py-4">
                            <h2 class="font-semibold">Cover</h2>
                        </div>

                        <div class="p-5">
                            <div class="flex aspect-[3/4] items-center justify-center overflow-hidden rounded-2xl border border-dashed border-border bg-muted/50">
                                <img v-if="coverPreview" :src="String(coverPreview)" alt="" class="size-full object-cover">
                                <div v-else class="text-center text-muted-foreground">
                                    <FileImage class="mx-auto size-8" />
                                    <p class="mt-2 text-sm">Belum ada cover</p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-border bg-background px-3 py-2 text-sm font-medium hover:bg-muted">
                                    <Upload class="size-4" />
                                    Pilih cover
                                    <input
                                        type="file"
                                        class="sr-only"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        @change="onCoverChange"
                                    >
                                </label>
                                <button
                                    v-if="coverPreview"
                                    type="button"
                                    class="rounded-xl px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                                    @click="removeCover"
                                >
                                    Hapus
                                </button>
                            </div>

                            <p class="mt-3 text-xs leading-5 text-muted-foreground">
                                JPG, PNG, atau WebP. Maksimal {{ maxCoverMb }} MB sesuai Settings.
                            </p>
                            <p v-if="form.errors.cover" class="mt-2 text-sm text-red-600">{{ form.errors.cover }}</p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-border bg-surface p-5">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 text-primary">
                                <CheckCircle2 v-if="!form.hasErrors" class="size-5" />
                                <XCircle v-else class="size-5 text-red-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    {{ form.hasErrors ? 'Periksa form' : 'Siap disimpan' }}
                                </p>
                                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                    Metadata dapat diubah kapan saja. Source PDF dikelola terpisah sehingga perubahan metadata tidak memindahkan file.
                                </p>
                            </div>
                        </div>

                        <Button class="mt-5 w-full" size="large" :disabled="form.processing">
                            <Save class="size-4" />
                            {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan perubahan' : 'Simpan ebook') }}
                        </Button>

                        <Link href="/admin/ebooks" class="mt-3 block text-center text-sm text-muted-foreground hover:text-foreground">
                            Batal
                        </Link>
                    </section>
                </aside>
            </form>
        </div>
    </AdminLayout>
</template>
