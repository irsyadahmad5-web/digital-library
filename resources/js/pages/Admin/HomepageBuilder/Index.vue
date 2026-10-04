<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Eye,
    GripVertical,
    PanelsTopLeft,
    Save,
    ShieldAlert,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import type { SharedPageProps } from '@/types';

interface BuilderField {
    label: string;
    type: 'text' | 'textarea' | 'number' | 'boolean';
    default: unknown;
    meta: {
        min?: number;
        max?: number;
        hint?: string;
    };
}

interface BuilderSchema {
    label: string;
    description: string;
    provider_available: boolean;
    provider_note: string | null;
    fields: Record<string, BuilderField>;
}

interface BuilderSection {
    id: number;
    type: string;
    title: string;
    is_enabled: boolean;
    sort_order: number;
    config: Record<string, any>;
    label: string;
    description: string;
    provider_available: boolean;
    provider_note: string | null;
}

const props = defineProps<{
    sections: BuilderSection[];
    schemas: Record<string, BuilderSchema>;
}>();

const page = usePage<SharedPageProps>();
const form = useForm({
    sections: props.sections.map((section) => ({
        id: section.id,
        type: section.type,
        title: section.title,
        is_enabled: section.is_enabled,
        config: { ...section.config },
    })),
});

const enabledCount = computed(() =>
    form.sections.filter((section) => section.is_enabled).length,
);

function move(index: number, direction: -1 | 1) {
    const target = index + direction;

    if (target < 0 || target >= form.sections.length) return;

    const copy = [...form.sections];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    form.sections = copy;
}

function submit() {
    form.put('/admin/homepage-builder', {
        preserveScroll: true,
    });
}

function sectionSchema(type: string) {
    return props.schemas[type];
}
</script>

<template>
    <Head title="Homepage Builder" />

    <AdminLayout>
        <div class="max-w-6xl">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <PanelsTopLeft class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-primary">CMS Homepage</p>
                        <h1 class="mt-1 text-3xl font-semibold tracking-tight">Homepage Builder</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Atur urutan, status, judul, dan konfigurasi section homepage tanpa mengedit source code.
                        </p>
                    </div>
                </div>

                <Link
                    href="/"
                    target="_blank"
                    class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-border bg-surface px-4 text-sm font-medium hover:bg-muted"
                >
                    <Eye class="size-4" />
                    Lihat homepage
                </Link>
            </div>

            <div v-if="page.props.flash.status" class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.status }}
            </div>

            <div class="mt-6 rounded-2xl border border-border bg-surface p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold">Susunan homepage</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ enabledCount }} dari {{ form.sections.length }} section diaktifkan.
                        </p>
                    </div>
                    <Button size="large" :disabled="form.processing" @click="submit">
                        <Save class="size-4" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Builder' }}
                    </Button>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <section
                    v-for="(section, index) in form.sections"
                    :key="section.id"
                    class="overflow-hidden rounded-2xl border border-border bg-surface"
                >
                    <div class="flex flex-col gap-4 border-b border-border px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                <GripVertical class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-semibold">
                                        {{ sectionSchema(section.type)?.label || section.type }}
                                    </h2>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[11px] font-medium"
                                        :class="sectionSchema(section.type)?.provider_available
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-amber-50 text-amber-700'"
                                    >
                                        {{ sectionSchema(section.type)?.provider_available ? 'Provider tersedia' : 'Provider belum tersedia' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm leading-5 text-muted-foreground">
                                    {{ sectionSchema(section.type)?.description }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-lg border border-border hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="index === 0"
                                aria-label="Naikkan section"
                                @click="move(index, -1)"
                            >
                                <ArrowUp class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-lg border border-border hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="index === form.sections.length - 1"
                                aria-label="Turunkan section"
                                @click="move(index, 1)"
                            >
                                <ArrowDown class="size-4" />
                            </button>

                            <label class="ml-1 inline-flex min-h-9 items-center gap-2 rounded-lg border border-border bg-background px-3 text-sm">
                                <input v-model="section.is_enabled" type="checkbox" class="size-4 rounded border-border">
                                <span>{{ section.is_enabled ? 'Aktif' : 'Nonaktif' }}</span>
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="!sectionSchema(section.type)?.provider_available"
                        class="border-b border-border bg-amber-50/70 px-5 py-3 text-sm text-amber-800 sm:px-6"
                    >
                        <div class="flex items-start gap-2">
                            <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                            <p>
                                {{ sectionSchema(section.type)?.provider_note }}
                                Konfigurasi tetap dapat disimpan, tetapi section tidak akan dirender ke publik sebelum provider tersedia.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-5 px-5 py-5 sm:px-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-8">
                        <div>
                            <label :for="`section-title-${section.id}`" class="text-sm font-medium">Nama internal section</label>
                            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                                Dipakai untuk identifikasi admin. Judul publik biasanya diatur pada field config.
                            </p>
                        </div>
                        <div>
                            <input
                                :id="`section-title-${section.id}`"
                                v-model="section.title"
                                type="text"
                                maxlength="255"
                                class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                            >
                        </div>
                    </div>

                    <div
                        v-for="(field, key) in sectionSchema(section.type)?.fields || {}"
                        :key="String(key)"
                        class="grid gap-5 border-t border-border px-5 py-5 sm:px-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-8"
                    >
                        <div>
                            <label :for="`${section.id}-${String(key)}`" class="text-sm font-medium">
                                {{ field.label }}
                            </label>
                            <p v-if="field.meta.hint" class="mt-1 text-xs leading-5 text-muted-foreground">
                                {{ field.meta.hint }}
                            </p>
                        </div>

                        <div>
                            <label
                                v-if="field.type === 'boolean'"
                                class="inline-flex min-h-11 items-center gap-3 rounded-xl border border-border bg-background px-4 text-sm"
                            >
                                <input v-model="section.config[String(key)]" type="checkbox" class="size-4 rounded border-border">
                                <span>{{ section.config[String(key)] ? 'Aktif' : 'Nonaktif' }}</span>
                            </label>

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                :id="`${section.id}-${String(key)}`"
                                v-model="section.config[String(key)]"
                                rows="3"
                                :maxlength="field.meta.max"
                                class="w-full rounded-xl border border-border bg-background px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                            />

                            <input
                                v-else
                                :id="`${section.id}-${String(key)}`"
                                v-model="section.config[String(key)]"
                                :type="field.type === 'number' ? 'number' : 'text'"
                                :min="field.type === 'number' ? field.meta.min : undefined"
                                :max="field.type === 'number' ? field.meta.max : undefined"
                                :maxlength="field.type === 'text' ? field.meta.max : undefined"
                                class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                            >
                        </div>
                    </div>
                </section>
            </div>

            <p v-if="form.errors.sections" class="mt-4 text-sm text-red-600">
                {{ form.errors.sections }}
            </p>

            <div class="mt-6 flex justify-end">
                <Button size="large" :disabled="form.processing" @click="submit">
                    <Save class="size-4" />
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Builder' }}
                </Button>
            </div>
        </div>
    </AdminLayout>
</template>
