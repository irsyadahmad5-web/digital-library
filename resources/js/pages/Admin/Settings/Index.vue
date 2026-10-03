<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Image as ImageIcon, Save, Settings2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
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

const activeDescription = computed(() =>
    props.groups.find((group) => group.key === props.activeGroup)?.description ?? '',
);

function fileInputName(field: FieldSchema) {
    return String(field.meta.input || '');
}

function onFileChange(event: Event, field: FieldSchema) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    const inputName = fileInputName(field);

    if (!inputName) return;

    form[inputName] = file;

    if (file) {
        previews.value[inputName] = URL.createObjectURL(file);
        form[`remove_${inputName}`] = false;
    }
}

function removeImage(field: FieldSchema) {
    const inputName = fileInputName(field);

    if (!inputName) return;

    form[inputName] = null;
    form[`remove_${inputName}`] = true;
    previews.value[inputName] = null;
}

function submit() {
    form.post(`/admin/settings/${props.activeGroup}`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan" />

    <AdminLayout>
        <div class="max-w-6xl">
            <div class="flex items-start gap-4">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <Settings2 class="size-5" />
                </div>
                <div>
                    <p class="text-sm font-medium text-primary">CMS Core</p>
                    <h1 class="mt-1 text-3xl font-semibold tracking-tight">Pengaturan Website</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Ubah konfigurasi website tanpa mengedit source code. Perubahan publik memakai cache dan akan langsung terbaca setelah disimpan.
                    </p>
                </div>
            </div>

            <div v-if="page.props.flash.status" class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.status }}
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                <aside class="rounded-2xl border border-border bg-surface p-3">
                    <nav class="space-y-1">
                        <Link
                            v-for="group in groups"
                            :key="group.key"
                            :href="`/admin/settings/${group.key}`"
                            class="block rounded-xl px-4 py-3 text-sm transition-colors"
                            :class="group.key === activeGroup
                                ? 'bg-primary text-primary-foreground'
                                : 'text-foreground hover:bg-muted'"
                        >
                            <div class="font-medium">{{ group.label }}</div>
                            <div
                                class="mt-1 text-xs leading-5"
                                :class="group.key === activeGroup ? 'text-primary-foreground/75' : 'text-muted-foreground'"
                            >
                                {{ group.description }}
                            </div>
                        </Link>
                    </nav>
                </aside>

                <section class="rounded-2xl border border-border bg-surface">
                    <div class="border-b border-border px-5 py-5 sm:px-7">
                        <h2 class="text-xl font-semibold">{{ schema.label }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ activeDescription }}</p>
                    </div>

                    <form class="divide-y divide-border" @submit.prevent="submit">
                        <div
                            v-for="(field, key) in schema.fields"
                            :key="key"
                            class="grid gap-3 px-5 py-5 sm:px-7 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-8"
                        >
                            <div>
                                <label :for="String(key)" class="text-sm font-medium">{{ field.label }}</label>
                                <p v-if="field.description" class="mt-1 text-xs leading-5 text-muted-foreground">
                                    {{ field.description }}
                                </p>
                                <span
                                    v-if="field.public"
                                    class="mt-2 inline-flex rounded-md bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-700"
                                >
                                    Dipakai publik
                                </span>
                            </div>

                            <div>
                                <template v-if="field.type === 'boolean'">
                                    <label class="inline-flex min-h-11 items-center gap-3 rounded-xl border border-border bg-background px-4 text-sm">
                                        <input v-model="form[String(key)]" type="checkbox" class="size-4 rounded border-border">
                                        <span>{{ form[String(key)] ? 'Aktif' : 'Nonaktif' }}</span>
                                    </label>
                                </template>

                                <textarea
                                    v-else-if="field.type === 'textarea'"
                                    :id="String(key)"
                                    v-model="form[String(key)]"
                                    rows="4"
                                    class="w-full rounded-xl border border-border bg-background px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                />

                                <select
                                    v-else-if="field.type === 'select'"
                                    :id="String(key)"
                                    v-model="form[String(key)]"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                >
                                    <option v-for="(label, value) in field.options" :key="value" :value="value">
                                        {{ label }}
                                    </option>
                                </select>

                                <div v-else-if="field.type === 'image'" class="space-y-3">
                                    <div
                                        class="flex min-h-28 items-center justify-center overflow-hidden rounded-xl border border-dashed border-border bg-muted/50 p-4"
                                    >
                                        <img
                                            v-if="previews[fileInputName(field)]"
                                            :src="String(previews[fileInputName(field)])"
                                            alt=""
                                            class="max-h-24 max-w-full object-contain"
                                        >
                                        <div v-else class="text-center text-muted-foreground">
                                            <ImageIcon class="mx-auto size-6" />
                                            <p class="mt-2 text-xs">Belum ada file</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <label class="cursor-pointer rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium hover:bg-muted">
                                            Pilih file
                                            <input
                                                type="file"
                                                class="sr-only"
                                                :accept="fileInputName(field) === 'favicon' ? '.png,.ico' : '.png,.jpg,.jpeg,.webp'"
                                                @change="onFileChange($event, field)"
                                            >
                                        </label>
                                        <button
                                            v-if="previews[fileInputName(field)]"
                                            type="button"
                                            class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                                            @click="removeImage(field)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>

                                <div v-else-if="field.type === 'color'" class="flex items-center gap-3">
                                    <input
                                        :id="String(key)"
                                        v-model="form[String(key)]"
                                        type="color"
                                        class="size-11 cursor-pointer rounded-lg border border-border bg-background p-1"
                                    >
                                    <input
                                        v-model="form[String(key)]"
                                        type="text"
                                        class="min-h-11 flex-1 rounded-xl border border-border bg-background px-4 font-mono text-sm uppercase outline-none"
                                    >
                                </div>

                                <input
                                    v-else
                                    :id="String(key)"
                                    v-model="form[String(key)]"
                                    :type="field.type === 'number' ? 'number' : field.type"
                                    :min="field.type === 'number' ? Number(field.meta.min) : undefined"
                                    :max="field.type === 'number' ? Number(field.meta.max) : undefined"
                                    class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                                >

                                <p v-if="form.errors[String(key)]" class="mt-2 text-sm text-red-600">
                                    {{ form.errors[String(key)] }}
                                </p>
                                <p
                                    v-if="field.type === 'image' && form.errors[fileInputName(field)]"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors[fileInputName(field)] }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 px-5 py-5 sm:px-7">
                            <Button size="large" :disabled="form.processing">
                                <Save class="size-4" />
                                {{ form.processing ? 'Menyimpan...' : 'Simpan pengaturan' }}
                            </Button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
