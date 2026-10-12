<script setup lang="ts">
import { onBeforeUnmount, reactive } from 'vue';
import {
    ArrowDown,
    ArrowUp,
    ImagePlus,
    Plus,
    Trash2,
    Upload,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';

interface HeroSlide {
    key: string;
    title: string;
    subtitle: string;
    alt: string;
    cta_label: string;
    cta_href: string;
    desktop_path?: string | null;
    mobile_path?: string | null;
    desktop_url?: string | null;
    mobile_url?: string | null;
    desktop_upload?: File | null;
    mobile_upload?: File | null;
    remove_desktop?: boolean;
    remove_mobile?: boolean;
}

const slides = defineModel<HeroSlide[]>({ default: () => [] });
const previews = reactive<Record<string, string>>({});

function previewKey(slide: HeroSlide, variant: 'desktop' | 'mobile') {
    return slide.key + ':' + variant;
}

function revokePreview(key: string) {
    const value = previews[key];

    if (!value) return;

    URL.revokeObjectURL(value);
    delete previews[key];
}

function preview(slide: HeroSlide, variant: 'desktop' | 'mobile') {
    const key = previewKey(slide, variant);
    const removed = variant === 'desktop' ? slide.remove_desktop : slide.remove_mobile;

    if (removed) return null;

    return previews[key]
        || (variant === 'desktop' ? slide.desktop_url : slide.mobile_url)
        || null;
}

function addSlide() {
    if (slides.value.length >= 6) return;

    slides.value.push({
        key: crypto.randomUUID(),
        title: '',
        subtitle: '',
        alt: '',
        cta_label: '',
        cta_href: '/library',
        desktop_path: null,
        mobile_path: null,
        desktop_url: null,
        mobile_url: null,
        desktop_upload: null,
        mobile_upload: null,
        remove_desktop: false,
        remove_mobile: false,
    });
}

function removeSlide(index: number) {
    const slide = slides.value[index];

    if (slide) {
        revokePreview(previewKey(slide, 'desktop'));
        revokePreview(previewKey(slide, 'mobile'));
    }

    slides.value.splice(index, 1);
}

function move(index: number, direction: -1 | 1) {
    const target = index + direction;

    if (target < 0 || target >= slides.value.length) return;

    const copy = [...slides.value];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    slides.value = copy;
}

function onImageChange(
    event: Event,
    slide: HeroSlide,
    variant: 'desktop' | 'mobile',
) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    const key = previewKey(slide, variant);

    revokePreview(key);

    if (variant === 'desktop') {
        slide.desktop_upload = file;
        slide.remove_desktop = false;
    } else {
        slide.mobile_upload = file;
        slide.remove_mobile = false;
    }

    if (file) {
        previews[key] = URL.createObjectURL(file);
    }
}

function removeImage(slide: HeroSlide, variant: 'desktop' | 'mobile') {
    const key = previewKey(slide, variant);
    revokePreview(key);

    if (variant === 'desktop') {
        slide.desktop_upload = null;
        slide.remove_desktop = true;
    } else {
        slide.mobile_upload = null;
        slide.remove_mobile = true;
    }
}

onBeforeUnmount(() => {
    for (const key of Object.keys(previews)) {
        revokePreview(key);
    }
});
</script>

<template>
    <div class="border-t border-line px-4 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold text-ink">Gambar slider</p>
                <p class="mt-1 max-w-3xl text-[11px] leading-5 text-ink-faint">
                    Slider publik hanya menampilkan gambar tanpa judul, deskripsi, overlay, atau tombol CTA. Maksimal 6 slide. Gunakan gambar horizontal terpisah untuk desktop dan mobile agar komposisi tetap tajam dan tidak terpotong berlebihan.
                </p>
            </div>
            <Button
                type="button"
                size="small"
                variant="secondary"
                :disabled="slides.length >= 6"
                @click="addSlide"
            >
                <Plus class="size-4" />
                Tambah slide
            </Button>
        </div>

        <div
            class="mt-4 rounded-[var(--radius-md)] border border-brand/20 bg-brand-soft/55 px-3 py-2.5 text-[11px] leading-5 text-ink-soft"
        >
            <strong class="text-ink">Konsep tampilan:</strong>
            full-width menempel tepat di bawah top bar, rasio horizontal di desktop maupun mobile, gambar memakai object-cover, dan kontrol navigasi transparan akan otomatis menghilang saat tidak digunakan.
        </div>

        <div v-if="slides.length" class="mt-4 grid gap-4">
            <article
                v-for="(slide, index) in slides"
                :key="slide.key"
                class="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface-subtle/45"
            >
                <header class="flex items-center justify-between gap-3 border-b border-line bg-surface px-3 py-2.5">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="grid size-7 place-items-center rounded-[var(--radius-sm)] bg-brand-soft text-[10px] font-semibold text-brand">
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold text-ink">Slide {{ index + 1 }}</p>
                            <p class="mt-0.5 text-[10px] text-ink-faint">Gambar saja · tanpa teks publik</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-1">
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink disabled:opacity-35"
                            :disabled="index === 0"
                            aria-label="Naikkan slide"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink disabled:opacity-35"
                            :disabled="index === slides.length - 1"
                            aria-label="Turunkan slide"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-danger-soft hover:text-danger"
                            aria-label="Hapus slide"
                            @click="removeSlide(index)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </header>

                <div class="grid gap-4 p-3 sm:p-4 xl:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <div>
                            <p class="text-xs font-semibold text-ink">Gambar desktop</p>
                            <p class="mt-0.5 text-[10px] leading-4 text-ink-faint">
                                Rekomendasi 1920 × 720 px · rasio 8:3 · horizontal · fokus penting di area tengah · JPG/PNG/WebP · maks. 8 MB
                            </p>
                        </div>

                        <div class="aspect-[8/3] overflow-hidden rounded-[var(--radius-md)] border border-dashed border-line bg-surface">
                            <img
                                v-if="preview(slide, 'desktop')"
                                :src="String(preview(slide, 'desktop'))"
                                alt=""
                                class="size-full object-cover"
                            >
                            <div v-else class="flex size-full flex-col items-center justify-center gap-2 px-3 text-center text-ink-faint">
                                <ImagePlus class="size-6" />
                                <span class="text-[10px]">Belum ada gambar desktop horizontal</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <label class="inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-[var(--radius-md)] border border-line bg-surface px-3 text-[11px] font-semibold text-ink hover:bg-surface-subtle">
                                <Upload class="size-3.5" />
                                Upload desktop
                                <input
                                    type="file"
                                    class="sr-only"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    @change="onImageChange($event, slide, 'desktop')"
                                >
                            </label>
                            <button
                                v-if="preview(slide, 'desktop')"
                                type="button"
                                class="min-h-9 rounded-[var(--radius-md)] px-2.5 text-[11px] font-semibold text-danger hover:bg-danger-soft"
                                @click="removeImage(slide, 'desktop')"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>

                    <div class="grid content-start gap-2">
                        <div>
                            <p class="text-xs font-semibold text-ink">Gambar mobile</p>
                            <p class="mt-0.5 text-[10px] leading-4 text-ink-faint">
                                Rekomendasi 1280 × 720 px · rasio 16:9 · tetap horizontal, bukan poster/kotak · JPG/PNG/WebP · maks. 8 MB
                            </p>
                        </div>

                        <div class="aspect-[16/9] overflow-hidden rounded-[var(--radius-md)] border border-dashed border-line bg-surface">
                            <img
                                v-if="preview(slide, 'mobile')"
                                :src="String(preview(slide, 'mobile'))"
                                alt=""
                                class="size-full object-cover"
                            >
                            <div v-else class="flex size-full flex-col items-center justify-center gap-2 px-3 text-center text-ink-faint">
                                <ImagePlus class="size-6" />
                                <span class="text-[10px]">Jika kosong, gambar desktop dipakai sebagai fallback.</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <label class="inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-[var(--radius-md)] border border-line bg-surface px-3 text-[11px] font-semibold text-ink hover:bg-surface-subtle">
                                <Upload class="size-3.5" />
                                Upload mobile
                                <input
                                    type="file"
                                    class="sr-only"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    @change="onImageChange($event, slide, 'mobile')"
                                >
                            </label>
                            <button
                                v-if="preview(slide, 'mobile')"
                                type="button"
                                class="min-h-9 rounded-[var(--radius-md)] px-2.5 text-[11px] font-semibold text-danger hover:bg-danger-soft"
                                @click="removeImage(slide, 'mobile')"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>

                    <label class="grid gap-1.5 xl:col-span-2">
                        <span class="text-[11px] font-semibold text-ink">Deskripsi gambar untuk aksesibilitas (alt)</span>
                        <input
                            v-model="slide.alt"
                            maxlength="180"
                            class="ui-control ui-focus-ring w-full px-3 text-sm"
                            placeholder="Contoh: Siswa membaca buku di perpustakaan"
                        >
                        <span class="text-[10px] leading-4 text-ink-faint">
                            Tidak tampil sebagai teks di slider. Digunakan pembaca layar dan membantu aksesibilitas.
                        </span>
                    </label>
                </div>
            </article>
        </div>

        <div
            v-else
            class="mt-4 rounded-[var(--radius-lg)] border border-dashed border-line bg-surface-subtle px-4 py-8 text-center"
        >
            <ImagePlus class="mx-auto size-6 text-ink-faint" />
            <p class="mt-2 text-xs font-semibold text-ink">Belum ada slide gambar</p>
            <p class="mt-1 text-[11px] text-ink-soft">Tambahkan slide pertama lalu upload gambar horizontal desktop dan mobile.</p>
            <Button type="button" size="small" class="mt-3" @click="addSlide">
                <Plus class="size-4" />
                Tambah slide
            </Button>
        </div>
    </div>
</template>
