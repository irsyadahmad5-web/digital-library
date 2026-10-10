<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Eye,
    GripVertical,
    Save,
    ShieldAlert,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/ui/page-header';
import { Switch } from '@/components/ui/switch';
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
        <div class="grid gap-5">
            <PageHeader
                eyebrow="Presentation"
                title="Homepage Builder"
                description="Atur urutan, status, judul, dan konfigurasi section homepage tanpa mengedit source code."
            >
                <template #actions>
                    <Button as-child size="small" variant="secondary">
                        <a href="/" target="_blank" rel="noopener">
                            <Eye class="size-4" />
                            Lihat homepage
                        </a>
                    </Button>
                    <Button size="small" :disabled="form.processing" @click="submit">
                        <Save class="size-4" />
                        {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                    </Button>
                </template>
            </PageHeader>

            <Alert v-if="page.props.flash.status" tone="success" :title="page.props.flash.status" />

            <div class="flex flex-col gap-3 rounded-[var(--radius-lg)] border border-line bg-surface px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-ink">Susunan homepage</p>
                    <p class="mt-1 text-xs text-ink-soft">
                        {{ enabledCount }} dari {{ form.sections.length }} section aktif.
                        <span v-if="form.isDirty" class="font-semibold text-warning">Ada perubahan belum disimpan.</span>
                    </p>
                </div>
                <p class="text-[11px] text-ink-faint">Gunakan tombol ↑ ↓ untuk mengatur urutan.</p>
            </div>

            <div class="grid gap-3">
                <section
                    v-for="(section, index) in form.sections"
                    :key="section.id"
                    class="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface"
                >
                    <div class="flex flex-col gap-3 border-b border-line px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] bg-surface-subtle text-ink-faint">
                                <span class="text-[10px] font-semibold tabular-nums">{{ String(index + 1).padStart(2, '0') }}</span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-sm font-semibold text-ink">
                                        {{ sectionSchema(section.type)?.label || section.type }}
                                    </h2>
                                    <Badge :tone="sectionSchema(section.type)?.provider_available ? 'success' : 'warning'">
                                        {{ sectionSchema(section.type)?.provider_available ? 'Provider siap' : 'Provider belum siap' }}
                                    </Badge>
                                    <Badge :tone="section.is_enabled ? 'brand' : 'neutral'">
                                        {{ section.is_enabled ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                </div>
                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-ink-soft">
                                    {{ sectionSchema(section.type)?.description }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            <button
                                type="button"
                                class="grid size-9 place-items-center rounded-[var(--radius-md)] border border-line text-ink-soft hover:bg-surface-subtle hover:text-ink disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="index === 0"
                                aria-label="Naikkan section"
                                @click="move(index, -1)"
                            >
                                <ArrowUp class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="grid size-9 place-items-center rounded-[var(--radius-md)] border border-line text-ink-soft hover:bg-surface-subtle hover:text-ink disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="index === form.sections.length - 1"
                                aria-label="Turunkan section"
                                @click="move(index, 1)"
                            >
                                <ArrowDown class="size-4" />
                            </button>

                            <div class="ml-1 flex min-h-9 items-center gap-2 rounded-[var(--radius-md)] border border-line px-2.5">
                                <span class="text-[11px] font-semibold text-ink-soft">{{ section.is_enabled ? 'On' : 'Off' }}</span>
                                <Switch v-model="section.is_enabled" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="!sectionSchema(section.type)?.provider_available"
                        class="flex items-start gap-2 border-b border-line bg-warning-soft px-4 py-3 text-xs leading-5 text-warning"
                    >
                        <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                        <p>
                            {{ sectionSchema(section.type)?.provider_note }}
                            Konfigurasi tetap dapat disimpan, tetapi section tidak dirender ke publik sampai provider tersedia.
                        </p>
                    </div>

                    <div class="grid gap-4 px-4 py-4 lg:grid-cols-[190px_minmax(0,1fr)] lg:gap-6">
                        <div>
                            <label :for="'section-title-' + section.id" class="text-xs font-semibold text-ink">Nama internal section</label>
                            <p class="mt-1 text-[11px] leading-5 text-ink-faint">
                                Untuk identifikasi admin. Judul publik diatur melalui field config.
                            </p>
                        </div>
                        <input
                            :id="'section-title-' + section.id"
                            v-model="section.title"
                            type="text"
                            maxlength="255"
                            class="ui-control ui-focus-ring w-full px-3 text-sm"
                        >
                    </div>

                    <div
                        v-for="(field, key) in sectionSchema(section.type)?.fields || {}"
                        :key="String(key)"
                        class="grid gap-4 border-t border-line px-4 py-4 lg:grid-cols-[190px_minmax(0,1fr)] lg:gap-6"
                    >
                        <div>
                            <label :for="section.id + '-' + String(key)" class="text-xs font-semibold text-ink">
                                {{ field.label }}
                            </label>
                            <p v-if="field.meta.hint" class="mt-1 text-[11px] leading-5 text-ink-faint">
                                {{ field.meta.hint }}
                            </p>
                        </div>

                        <div>
                            <div
                                v-if="field.type === 'boolean'"
                                class="flex max-w-sm items-center justify-between gap-4 rounded-[var(--radius-md)] border border-line px-3 py-3"
                            >
                                <span class="text-xs text-ink-soft">{{ section.config[String(key)] ? 'Aktif' : 'Nonaktif' }}</span>
                                <Switch v-model="section.config[String(key)]" />
                            </div>

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                :id="section.id + '-' + String(key)"
                                v-model="section.config[String(key)]"
                                rows="3"
                                :maxlength="field.meta.max"
                                class="ui-control ui-focus-ring w-full px-3 py-2.5 text-sm leading-6"
                            />

                            <input
                                v-else
                                :id="section.id + '-' + String(key)"
                                v-model="section.config[String(key)]"
                                :type="field.type === 'number' ? 'number' : 'text'"
                                :min="field.type === 'number' ? field.meta.min : undefined"
                                :max="field.type === 'number' ? field.meta.max : undefined"
                                :maxlength="field.type === 'text' ? field.meta.max : undefined"
                                class="ui-control ui-focus-ring w-full px-3 text-sm"
                            >
                        </div>
                    </div>
                </section>
            </div>

            <Alert v-if="form.errors.sections" tone="danger" title="Builder belum dapat disimpan">
                {{ form.errors.sections }}
            </Alert>

            <div class="sticky bottom-3 z-20 flex justify-end pointer-events-none">
                <Button
                    class="pointer-events-auto shadow-[var(--shadow-float)]"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Save class="size-4" />
                    {{ form.processing ? 'Menyimpan…' : (form.isDirty ? 'Simpan perubahan' : 'Simpan Builder') }}
                </Button>
            </div>
        </div>
    </AdminLayout>
</template>
