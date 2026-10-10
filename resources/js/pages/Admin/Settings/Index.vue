<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Image as ImageIcon, Save, Upload } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import type { SharedPageProps } from '@/types';

interface GroupMeta {
    key: string;
    label: string;
    description: string;
}

interface FieldSchema {
    label: string;
    type: 'text' | 'textarea' | 'email' | 'url' | 'number' | 'boolean' | 'select' | 'color' | 'image';
    default: unknown;
    public: boolean;
    encrypted: boolean;
    description: string;
    options: Record<string, string>;
    meta: Record<string, unknown>;
}

interface GroupSchema {
    label: string;
    description: string;
    fields: Record<string, FieldSchema>;
}

const props = defineProps<{
    groups: GroupMeta[];
    activeGroup: string;
    schema: GroupSchema;
    values: Record<string, unknown>;
    media: {
        logo_url: string | null;
        favicon_url: string | null;
    };
}>();

const page = usePage<SharedPageProps>();
const form = useForm<Record<string, any>>({
    ...props.values,
    logo: null,
    favicon: null,
    remove_logo: false,
    remove_favicon: false,
});

const previews = ref<Record<string, string | null>>({
    logo: props.media.logo_url,
    favicon: props.media.favicon_url,
});

const localObjectUrls = new Map<string, string>();

const activeDescription = computed(() =>
    props.groups.find((group) => group.key === props.activeGroup)?.description ?? '',
);

function fileInputName(field: FieldSchema) {
    return String(field.meta.input || '');
}

function revokeLocalPreview(inputName: string) {
    const url = localObjectUrls.get(inputName);
    if (!url) return;

    URL.revokeObjectURL(url);
    localObjectUrls.delete(inputName);
}

function onFileChange(event: Event, field: FieldSchema) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    const inputName = fileInputName(field);

    if (!inputName) return;

    revokeLocalPreview(inputName);
    form[inputName] = file;

    if (file) {
        const url = URL.createObjectURL(file);
        localObjectUrls.set(inputName, url);
        previews.value[inputName] = url;
        form['remove_' + inputName] = false;
    }
}

function removeImage(field: FieldSchema) {
    const inputName = fileInputName(field);

    if (!inputName) return;

    revokeLocalPreview(inputName);
    form[inputName] = null;
    form['remove_' + inputName] = true;
    previews.value[inputName] = null;
}

function submit() {
    form.post('/admin/settings/' + props.activeGroup, {
        forceFormData: true,
        preserveScroll: true,
    });
}

onBeforeUnmount(() => {
    for (const inputName of localObjectUrls.keys()) {
        revokeLocalPreview(inputName);
    }
});
</script>

<template>
    <Head title="Pengaturan" />

    <AdminLayout>
        <div class="grid gap-5">
            <PageHeader
                eyebrow="Presentation"
                title="Pengaturan Website"
                description="Kelola konfigurasi publik, appearance, reader, download, SEO, dan operasional tanpa mengedit source code."
            />

            <Alert v-if="page.props.flash.status" tone="success" :title="page.props.flash.status" />

            <div class="grid gap-4 lg:grid-cols-[210px_minmax(0,1fr)]">
                <aside class="-mx-1 overflow-x-auto px-1 pb-1 lg:mx-0 lg:overflow-visible lg:px-0 lg:pb-0">
                    <nav class="flex min-w-max gap-1 lg:sticky lg:top-20 lg:grid lg:min-w-0" aria-label="Kelompok pengaturan">
                        <Link
                            v-for="group in groups"
                            :key="group.key"
                            :href="'/admin/settings/' + group.key"
                            class="min-h-10 rounded-[var(--radius-md)] px-3 py-2.5 text-xs transition-colors lg:block"
                            :class="group.key === activeGroup
                                ? 'bg-brand text-brand-foreground'
                                : 'border border-line bg-surface text-ink-soft hover:bg-surface-subtle hover:text-ink'"
                            :aria-current="group.key === activeGroup ? 'page' : undefined"
                        >
                            <span class="font-semibold">{{ group.label }}</span>
                            <span
                                class="mt-1 hidden text-[10px] leading-4 lg:block"
                                :class="group.key === activeGroup ? 'text-brand-foreground/75' : 'text-ink-faint'"
                            >
                                {{ group.description }}
                            </span>
                        </Link>
                    </nav>
                </aside>

                <section class="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface">
                    <div class="flex flex-col gap-3 border-b border-line px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div>
                            <h2 class="text-base font-semibold text-ink">{{ schema.label }}</h2>
                            <p class="mt-1 text-xs leading-5 text-ink-soft">{{ activeDescription }}</p>
                        </div>
                        <span v-if="form.isDirty" class="text-[11px] font-semibold text-warning">Ada perubahan belum disimpan</span>
                    </div>

                    <form class="divide-y divide-line" @submit.prevent="submit">
                        <div
                            v-for="(field, key) in schema.fields"
                            :key="key"
                            class="grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[200px_minmax(0,1fr)] lg:gap-6"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <label :for="String(key)" class="text-xs font-semibold text-ink">{{ field.label }}</label>
                                    <Badge v-if="field.public" tone="brand">Publik</Badge>
                                    <Badge v-if="field.encrypted" tone="neutral">Terenkripsi</Badge>
                                </div>
                                <p v-if="field.description" class="mt-1 text-[11px] leading-5 text-ink-faint">
                                    {{ field.description }}
                                </p>
                            </div>

                            <div class="min-w-0">
                                <div
                                    v-if="field.type === 'boolean'"
                                    class="flex max-w-sm items-center justify-between gap-4 rounded-[var(--radius-md)] border border-line px-3 py-3"
                                >
                                    <span class="text-xs text-ink-soft">{{ form[String(key)] ? 'Aktif' : 'Nonaktif' }}</span>
                                    <Switch v-model="form[String(key)]" />
                                </div>

                                <textarea
                                    v-else-if="field.type === 'textarea'"
                                    :id="String(key)"
                                    v-model="form[String(key)]"
                                    rows="4"
                                    class="ui-control ui-focus-ring w-full px-3 py-2.5 text-sm leading-6"
                                />

                                <Select
                                    v-else-if="field.type === 'select'"
                                    :id="String(key)"
                                    v-model="form[String(key)]"
                                >
                                    <option v-for="(label, value) in field.options" :key="value" :value="value">
                                        {{ label }}
                                    </option>
                                </Select>

                                <div v-else-if="field.type === 'image'" class="grid gap-3 sm:grid-cols-[160px_minmax(0,1fr)] sm:items-center">
                                    <div class="flex min-h-28 items-center justify-center overflow-hidden rounded-[var(--radius-md)] border border-dashed border-line bg-surface-subtle p-3">
                                        <img
                                            v-if="previews[fileInputName(field)]"
                                            :src="String(previews[fileInputName(field)])"
                                            alt=""
                                            class="max-h-24 max-w-full object-contain"
                                        >
                                        <div v-else class="text-center text-ink-faint">
                                            <ImageIcon class="mx-auto size-5" />
                                            <p class="mt-1.5 text-[11px]">Belum ada file</p>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex flex-wrap gap-2">
                                            <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-[var(--radius-md)] border border-line bg-surface px-3 text-xs font-semibold text-ink hover:bg-surface-subtle">
                                                <Upload class="size-4" />
                                                Pilih file
                                                <input
                                                    type="file"
                                                    class="sr-only"
                                                    :accept="fileInputName(field) === 'favicon' ? '.png,.ico' : '.png,.jpg,.jpeg,.webp'"
                                                    @change="onFileChange($event, field)"
                                                >
                                            </label>
                                            <Button
                                                v-if="previews[fileInputName(field)]"
                                                type="button"
                                                size="small"
                                                variant="quiet"
                                                class="text-danger hover:bg-danger-soft hover:text-danger"
                                                @click="removeImage(field)"
                                            >
                                                Hapus
                                            </Button>
                                        </div>
                                        <p class="mt-2 text-[11px] text-ink-faint">
                                            Gunakan file yang tajam dan proporsional agar konsisten di seluruh perangkat.
                                        </p>
                                    </div>
                                </div>

                                <div v-else-if="field.type === 'color'" class="flex items-center gap-2">
                                    <input
                                        :id="String(key)"
                                        v-model="form[String(key)]"
                                        type="color"
                                        class="size-10 shrink-0 cursor-pointer rounded-[var(--radius-md)] border border-line bg-surface p-1"
                                    >
                                    <input
                                        v-model="form[String(key)]"
                                        type="text"
                                        class="ui-control ui-focus-ring min-w-0 flex-1 px-3 font-mono text-sm uppercase"
                                    >
                                </div>

                                <input
                                    v-else
                                    :id="String(key)"
                                    v-model="form[String(key)]"
                                    :type="field.type === 'number' ? 'number' : field.type"
                                    :min="field.type === 'number' ? Number(field.meta.min) : undefined"
                                    :max="field.type === 'number' ? Number(field.meta.max) : undefined"
                                    class="ui-control ui-focus-ring w-full px-3 text-sm"
                                >

                                <p v-if="form.errors[String(key)]" class="mt-1.5 text-xs text-danger">
                                    {{ form.errors[String(key)] }}
                                </p>
                                <p
                                    v-if="field.type === 'image' && form.errors[fileInputName(field)]"
                                    class="mt-1.5 text-xs text-danger"
                                >
                                    {{ form.errors[fileInputName(field)] }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 px-4 py-4 sm:px-5">
                            <Button :disabled="form.processing">
                                <Save class="size-4" />
                                {{ form.processing ? 'Menyimpan…' : 'Simpan pengaturan' }}
                            </Button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
