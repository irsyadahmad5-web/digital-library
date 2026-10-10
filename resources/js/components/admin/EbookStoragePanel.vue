<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Cloud,
    FileText,
    Link2,
    LoaderCircle,
    RotateCcw,
    ShieldCheck,
    Trash2,
    Upload,
    XCircle,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';

interface FileSource {
    id: number;
    source_type: 'local' | 'external_url';
    original_name: string | null;
    external_url: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    sha256: string | null;
    verification_status: 'verified' | 'skipped' | 'failed' | string;
    processing_status: 'pending' | 'processing' | 'processed' | 'failed' | string;
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

interface UploadSession {
    id: string;
    original_name: string;
    total_size: number;
    chunk_size: number;
    total_chunks: number;
    received_chunks: number;
    received_bytes: number;
    received_indices: number[];
    received_chunks_meta: Array<{
        index: number;
        size_bytes: number;
        sha256: string;
    }>;
    status: string;
    expires_at: string;
    completed_at: string | null;
    last_error: string | null;
}

class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

const props = defineProps<{
    ebookId: number;
    source: FileSource | null;
    config: UploadConfig;
    processingConfig: ProcessingConfig;
}>();

const mode = ref<'local' | 'external_url'>(
    props.source?.source_type ?? props.config.preferred_source,
);
const selectedFile = ref<File | null>(null);
const session = ref<UploadSession | null>(null);
const uploading = ref(false);
const cancelling = ref(false);
const uploadError = ref('');
const externalUrl = ref(
    props.source?.source_type === 'external_url'
        ? props.source.external_url ?? ''
        : '',
);
const externalBusy = ref(false);
const externalError = ref('');
const removingSource = ref(false);
const processingBusy = ref(false);
const processingError = ref('');

watch(
    () => props.source,
    (source) => {
        if (source?.source_type) {
            mode.value = source.source_type;
        }

        if (source?.source_type === 'external_url') {
            externalUrl.value = source.external_url ?? '';
        }

        processingError.value = '';
    },
);

const progress = computed(() => {
    if (!session.value || session.value.total_size <= 0) return 0;

    return Math.min(
        100,
        Math.round((session.value.received_bytes / session.value.total_size) * 100),
    );
});

const effectiveChunkLabel = computed(() =>
    formatBytes(props.config.effective_chunk_bytes),
);

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function apiFetch(url: string, options: RequestInit = {}) {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-CSRF-TOKEN', csrfToken());

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers,
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = payload?.errors as Record<string, string[]> | undefined;
        const firstError = errors
            ? Object.values(errors).flat().find((value) => typeof value === 'string')
            : null;

        throw new ApiError(
            firstError || payload?.message || `Request gagal (HTTP ${response.status}).`,
            response.status,
        );
    }

    return payload;
}

function onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    selectedFile.value = file;
    uploadError.value = '';

    if (!file) return;

    if (!file.name.toLowerCase().endsWith('.pdf')) {
        uploadError.value = 'File harus berekstensi .pdf.';
        selectedFile.value = null;
        input.value = '';
        return;
    }

    if (file.size > props.config.max_pdf_bytes) {
        uploadError.value = `Ukuran file melebihi batas ${props.config.max_pdf_mb} MB.`;
        selectedFile.value = null;
        input.value = '';
    }
}

async function startOrResumeUpload() {
    if (!selectedFile.value || uploading.value) return;

    uploadError.value = '';
    uploading.value = true;

    try {
        const file = selectedFile.value;

        const started = await apiFetch(
            `/admin/ebooks/${props.ebookId}/uploads`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    file_name: file.name,
                    size_bytes: file.size,
                }),
            },
        );

        session.value = started.session as UploadSession;
        const received = new Set(session.value.received_indices);
        const receivedHashes = new Map(
            session.value.received_chunks_meta.map((chunk) => [chunk.index, chunk.sha256]),
        );

        for (let index = 0; index < session.value.total_chunks; index++) {
            const start = index * session.value.chunk_size;
            const end = Math.min(start + session.value.chunk_size, file.size);
            const blob = file.slice(start, end);
            const chunkSha256 = props.config.checksum_enabled
                ? await sha256Blob(blob)
                : null;

            if (received.has(index)) {
                if (!props.config.checksum_enabled) continue;

                if (
                    chunkSha256
                    && receivedHashes.get(index)
                    && receivedHashes.get(index) === chunkSha256
                ) {
                    continue;
                }
            }

            const formData = new FormData();
            formData.append('chunk', blob, `${file.name}.part-${index}`);

            if (chunkSha256) {
                formData.append('chunk_sha256', chunkSha256);
            }

            const uploaded = await withRetry(async () =>
                apiFetch(
                    `/admin/ebooks/${props.ebookId}/uploads/${session.value?.id}/chunks/${index}`,
                    {
                        method: 'POST',
                        body: formData,
                    },
                ),
            );

            session.value = uploaded.session as UploadSession;
        }

        const completed = await apiFetch(
            `/admin/ebooks/${props.ebookId}/uploads/${session.value.id}/complete`,
            { method: 'POST' },
        );

        if (completed.source) {
            selectedFile.value = null;
            session.value = null;
            router.reload({ only: ['fileSource'] });
        }
    } catch (error) {
        uploadError.value = error instanceof Error
            ? error.message
            : 'Upload gagal. Anda dapat mencoba lanjutkan kembali.';
    } finally {
        uploading.value = false;
    }
}

async function withRetry<T>(callback: () => Promise<T>): Promise<T> {
    let lastError: unknown;

    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            return await callback();
        } catch (error) {
            lastError = error;

            if (error instanceof ApiError && error.status >= 400 && error.status < 500 && error.status !== 408 && error.status !== 429) {
                throw error;
            }

            if (attempt < 2) {
                await delay(500 * (2 ** attempt));
            }
        }
    }

    throw lastError;
}

async function cancelUpload() {
    if (!session.value || cancelling.value) return;

    cancelling.value = true;

    try {
        await apiFetch(
            `/admin/ebooks/${props.ebookId}/uploads/${session.value.id}`,
            { method: 'DELETE' },
        );
        session.value = null;
        selectedFile.value = null;
        uploadError.value = '';
    } catch (error) {
        uploadError.value = error instanceof Error ? error.message : 'Sesi upload gagal dibatalkan.';
    } finally {
        cancelling.value = false;
    }
}

async function attachExternal() {
    if (!externalUrl.value.trim() || externalBusy.value) return;

    externalBusy.value = true;
    externalError.value = '';

    try {
        await apiFetch(
            `/admin/ebooks/${props.ebookId}/storage/external`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ external_url: externalUrl.value.trim() }),
            },
        );

        router.reload({ only: ['fileSource'] });
    } catch (error) {
        externalError.value = error instanceof Error
            ? error.message
            : 'URL eksternal gagal disimpan.';
    } finally {
        externalBusy.value = false;
    }
}

async function removeSource() {
    if (!props.source || removingSource.value) return;

    if (!window.confirm('Lepaskan source PDF dari ebook ini?')) return;

    removingSource.value = true;

    try {
        await apiFetch(
            `/admin/ebooks/${props.ebookId}/storage`,
            { method: 'DELETE' },
        );
        session.value = null;
        selectedFile.value = null;
        processingError.value = '';
        router.reload({ only: ['fileSource', 'ebook'] });
    } catch (error) {
        uploadError.value = error instanceof Error
            ? error.message
            : 'Source PDF gagal dilepas.';
    } finally {
        removingSource.value = false;
    }
}

async function processPdf() {
    if (!props.source || processingBusy.value || !props.processingConfig.available) return;

    processingBusy.value = true;
    processingError.value = '';

    try {
        await apiFetch(
            `/admin/ebooks/${props.ebookId}/processing`,
            { method: 'POST' },
        );

        router.reload({ only: ['fileSource', 'ebook'] });
    } catch (error) {
        processingError.value = error instanceof Error
            ? error.message
            : 'PDF gagal diproses.';
        router.reload({ only: ['fileSource', 'ebook'] });
    } finally {
        processingBusy.value = false;
    }
}

function processingLabel(status: string) {
    return {
        pending: 'Menunggu pemrosesan',
        processing: 'Sedang diproses',
        processed: 'Sudah diproses',
        failed: 'Pemrosesan gagal',
    }[status] ?? status;
}

function processingClass(status: string) {
    return {
        pending: 'bg-amber-50 text-amber-700',
        processing: 'bg-blue-50 text-blue-700',
        processed: 'bg-emerald-50 text-emerald-700',
        failed: 'bg-red-50 text-red-700',
    }[status] ?? 'bg-surface-subtle text-ink-soft';
}

function metadataText(key: string) {
    const value = props.source?.pdf_metadata?.[key];

    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';

    return String(value);
}

function formatBytes(value: number | null | undefined) {
    if (value === null || value === undefined) return 'Ukuran tidak diketahui';
    if (value < 1024) return `${value} B`;

    const units = ['KB', 'MB', 'GB'];
    let size = value / 1024;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }

    return `${size.toFixed(size >= 10 ? 1 : 2)} ${units[unitIndex]}`;
}

async function sha256Blob(blob: Blob): Promise<string | null> {
    if (!window.crypto?.subtle) return null;

    const buffer = await blob.arrayBuffer();
    const digest = await window.crypto.subtle.digest('SHA-256', buffer);

    return Array.from(new Uint8Array(digest))
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

function delay(milliseconds: number) {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

function verificationLabel(source: FileSource) {
    if (source.verification_status === 'verified') return 'Terverifikasi';
    if (source.verification_status === 'skipped') return 'Verifikasi network dimatikan';

    return source.verification_status;
}
</script>

<template>
    <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
        <div class="flex flex-col gap-4 border-b border-line px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-7">
            <div>
                <div class="flex items-center gap-2">
                    <FileText class="size-5 text-brand" />
                    <h2 class="text-lg font-semibold">File PDF</h2>
                </div>
                <p class="mt-1 text-sm leading-6 text-ink-soft">
                    Upload private storage atau hubungkan PDF langsung dari cloud/URL eksternal.
                </p>
            </div>

            <button
                v-if="source"
                type="button"
                class="inline-flex items-center gap-2 text-sm text-red-600 hover:underline"
                :disabled="removingSource"
                @click="removeSource"
            >
                <Trash2 class="size-4" />
                {{ removingSource ? 'Melepas...' : 'Lepaskan source' }}
            </button>
        </div>

        <div v-if="source" class="border-b border-line bg-surface-subtle/70 px-5 py-4 sm:px-7">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                            <ShieldCheck class="size-3.5" />
                            {{ verificationLabel(source) }}
                        </span>
                        <span class="rounded-full bg-canvas px-2.5 py-1 text-xs font-medium text-ink-soft">
                            {{ source.source_type === 'local' ? 'Private Local' : 'External URL' }}
                        </span>
                        <span v-if="source.size_bytes !== null" class="text-xs text-ink-soft">
                            {{ formatBytes(source.size_bytes) }}
                        </span>
                    </div>

                    <p v-if="source.source_type === 'local'" class="mt-3 truncate text-sm font-medium">
                        {{ source.original_name || 'ebook.pdf' }}
                    </p>
                    <p v-else class="mt-3 truncate text-sm font-medium" :title="source.external_url || ''">
                        {{ source.external_url }}
                    </p>

                    <p v-if="source.sha256" class="mt-2 break-all font-mono text-[11px] leading-5 text-ink-soft">
                        SHA-256 {{ source.sha256 }}
                    </p>
                </div>

                <CheckCircle2 class="size-6 shrink-0 text-emerald-600" />
            </div>
        </div>

        <div v-if="source" class="border-b border-line px-5 py-5 sm:px-7">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold">PDF Processing</p>
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="processingClass(source.processing_status)"
                        >
                            {{ processingLabel(source.processing_status) }}
                        </span>
                    </div>
                    <p class="mt-2 max-w-2xl text-xs leading-5 text-ink-soft">
                        Metadata dan thumbnail diproses dengan pdfinfo + pdftocairo. Source pending juga diproses otomatis oleh scheduler.
                    </p>
                </div>

                <Button
                    type="button"
                    variant="secondary"
                    :disabled="processingBusy || source.processing_status === 'processing' || !processingConfig.available"
                    @click="processPdf"
                >
                    <LoaderCircle v-if="processingBusy || source.processing_status === 'processing'" class="size-4 animate-spin" />
                    <RotateCcw v-else class="size-4" />
                    {{ processingBusy ? 'Memproses...' : (source.processing_status === 'processed' ? 'Proses ulang' : 'Proses sekarang') }}
                </Button>
            </div>

            <div
                v-if="!processingConfig.available"
                class="mt-4 rounded-[var(--radius-md)] bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                Toolchain PDF server belum lengkap.
                pdfinfo: {{ processingConfig.pdfinfo_available ? 'tersedia' : 'tidak tersedia' }},
                pdftocairo: {{ processingConfig.pdftocairo_available ? 'tersedia' : 'tidak tersedia' }}.
            </div>

            <p
                v-if="processingError || source.processing_error"
                class="mt-4 flex items-start gap-2 rounded-[var(--radius-md)] bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <XCircle class="mt-0.5 size-4 shrink-0" />
                {{ processingError || source.processing_error }}
            </p>

            <div v-if="source.processing_status === 'processed'" class="mt-5 grid gap-5 lg:grid-cols-[180px_minmax(0,1fr)]">
                <div class="overflow-hidden rounded-[var(--radius-md)] border border-line bg-surface-subtle">
                    <img
                        v-if="source.preview_url"
                        :src="source.preview_url"
                        alt="Preview halaman pertama PDF"
                        class="aspect-[3/4] w-full object-cover"
                    >
                    <div v-else class="flex aspect-[3/4] items-center justify-center text-ink-soft">
                        <FileText class="size-8" />
                    </div>
                </div>

                <div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-[var(--radius-md)] bg-surface-subtle px-4 py-3">
                            <p class="text-xs text-ink-soft">Jumlah halaman</p>
                            <p class="mt-1 text-sm font-semibold">{{ source.page_count ?? '—' }}</p>
                        </div>
                        <div class="rounded-[var(--radius-md)] bg-surface-subtle px-4 py-3">
                            <p class="text-xs text-ink-soft">PDF version</p>
                            <p class="mt-1 text-sm font-semibold">{{ metadataText('pdf_version') }}</p>
                        </div>
                        <div class="rounded-[var(--radius-md)] bg-surface-subtle px-4 py-3">
                            <p class="text-xs text-ink-soft">Page size</p>
                            <p class="mt-1 truncate text-sm font-semibold" :title="metadataText('page_size')">
                                {{ metadataText('page_size') }}
                            </p>
                        </div>
                        <div class="rounded-[var(--radius-md)] bg-surface-subtle px-4 py-3">
                            <p class="text-xs text-ink-soft">Title metadata</p>
                            <p class="mt-1 truncate text-sm font-semibold" :title="metadataText('title')">
                                {{ metadataText('title') }}
                            </p>
                        </div>
                        <div class="rounded-[var(--radius-md)] bg-surface-subtle px-4 py-3">
                            <p class="text-xs text-ink-soft">Author metadata</p>
                            <p class="mt-1 truncate text-sm font-semibold" :title="metadataText('author')">
                                {{ metadataText('author') }}
                            </p>
                        </div>
                        <div class="rounded-[var(--radius-md)] bg-surface-subtle px-4 py-3">
                            <p class="text-xs text-ink-soft">Optimized</p>
                            <p class="mt-1 text-sm font-semibold">{{ metadataText('optimized') }}</p>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-[var(--radius-md)] border border-line px-4 py-3">
                            <p class="text-xs text-ink-soft">Creator</p>
                            <p class="mt-1 truncate text-sm" :title="metadataText('creator')">{{ metadataText('creator') }}</p>
                        </div>
                        <div class="rounded-[var(--radius-md)] border border-line px-4 py-3">
                            <p class="text-xs text-ink-soft">Producer</p>
                            <p class="mt-1 truncate text-sm" :title="metadataText('producer')">{{ metadataText('producer') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-5 sm:p-7">
            <div class="inline-flex rounded-[var(--radius-md)] border border-line bg-surface-subtle p-1">
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="mode === 'local' ? 'bg-surface text-ink shadow-sm' : 'text-ink-soft'"
                    @click="mode = 'local'"
                >
                    <span class="inline-flex items-center gap-2">
                        <Upload class="size-4" />
                        Upload lokal
                    </span>
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="mode === 'external_url' ? 'bg-surface text-ink shadow-sm' : 'text-ink-soft'"
                    @click="mode = 'external_url'"
                >
                    <span class="inline-flex items-center gap-2">
                        <Cloud class="size-4" />
                        URL cloud
                    </span>
                </button>
            </div>

            <div v-if="mode === 'local'" class="mt-6">
                <div class="rounded-[var(--radius-lg)] border border-dashed border-line bg-canvas p-5">
                    <label class="block cursor-pointer">
                        <div class="flex flex-col items-center justify-center py-5 text-center">
                            <Upload class="size-8 text-brand" />
                            <p class="mt-3 font-medium">
                                {{ selectedFile ? selectedFile.name : 'Pilih file PDF' }}
                            </p>
                            <p class="mt-1 text-sm text-ink-soft">
                                Maksimal {{ config.max_pdf_mb }} MB · chunk efektif {{ effectiveChunkLabel }}
                            </p>
                            <p v-if="selectedFile" class="mt-2 text-xs text-ink-soft">
                                {{ formatBytes(selectedFile.size) }}
                            </p>
                        </div>
                        <input
                            type="file"
                            class="sr-only"
                            accept=".pdf,application/pdf"
                            :disabled="uploading"
                            @change="onFileSelected"
                        >
                    </label>
                </div>

                <div v-if="session" class="mt-4 rounded-[var(--radius-md)] border border-line bg-canvas p-4">
                    <div class="flex items-center justify-between gap-4 text-sm">
                        <span class="font-medium">{{ uploading ? 'Mengunggah...' : 'Upload dapat dilanjutkan' }}</span>
                        <span class="tabular-nums text-ink-soft">{{ progress }}%</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-subtle">
                        <div
                            class="h-full rounded-full bg-primary transition-[width] duration-200"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-ink-soft">
                        <span>
                            {{ session.received_chunks }} / {{ session.total_chunks }} chunk ·
                            {{ formatBytes(session.received_bytes) }} diterima
                        </span>
                        <button
                            type="button"
                            class="text-red-600 hover:underline"
                            :disabled="uploading || cancelling"
                            @click="cancelUpload"
                        >
                            {{ cancelling ? 'Membatalkan...' : 'Batalkan sesi' }}
                        </button>
                    </div>
                </div>

                <p v-if="uploadError" class="mt-3 flex items-start gap-2 text-sm text-red-600">
                    <XCircle class="mt-0.5 size-4 shrink-0" />
                    {{ uploadError }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        :disabled="!selectedFile || uploading"
                        @click="startOrResumeUpload"
                    >
                        <LoaderCircle v-if="uploading" class="size-4 animate-spin" />
                        <RotateCcw v-else-if="session" class="size-4" />
                        <Upload v-else class="size-4" />
                        {{ uploading ? 'Memproses upload...' : (session ? 'Lanjutkan upload' : 'Mulai upload') }}
                    </Button>
                </div>

                <div class="mt-5 rounded-[var(--radius-md)] bg-surface-subtle px-4 py-3 text-xs leading-5 text-ink-soft">
                    Upload dapat diulang per chunk hingga 3 kali saat koneksi terganggu. Jika halaman tertutup, pilih kembali file yang sama sebelum session kedaluwarsa; server akan melewati chunk yang sudah diterima. Saat checksum aktif, SHA-256 dihitung per chunk di browser dan diverifikasi server; setelah assembly server menghitung SHA-256 final.
                </div>
            </div>

            <div v-else class="mt-6">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium">URL PDF eksternal</span>
                    <div class="flex min-h-11 items-center gap-2 rounded-[var(--radius-md)] border border-line bg-canvas px-3">
                        <Link2 class="size-4 shrink-0 text-ink-soft" />
                        <input
                            v-model="externalUrl"
                            type="url"
                            class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                            :placeholder="config.https_only_external ? 'https://.../ebook.pdf' : 'https://... atau http://...'"
                            :disabled="externalBusy"
                        >
                    </div>
                </label>

                <p class="mt-2 text-xs leading-5 text-ink-soft">
                    {{ config.verify_external_urls
                        ? 'Server akan mengikuti redirect secara terbatas, menolak alamat private/internal, memeriksa ukuran, dan membaca signature PDF.'
                        : 'Verifikasi isi URL sedang dimatikan di Settings, tetapi proteksi URL internal tetap diterapkan.' }}
                </p>

                <p v-if="externalError" class="mt-3 flex items-start gap-2 text-sm text-red-600">
                    <XCircle class="mt-0.5 size-4 shrink-0" />
                    {{ externalError }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        :disabled="!externalUrl.trim() || externalBusy"
                        @click="attachExternal"
                    >
                        <LoaderCircle v-if="externalBusy" class="size-4 animate-spin" />
                        <Cloud v-else class="size-4" />
                        {{ externalBusy
                            ? 'Memverifikasi...'
                            : (source?.source_type === 'external_url' ? 'Verifikasi & simpan ulang' : 'Verifikasi & gunakan URL') }}
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
