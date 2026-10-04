<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, LibraryBig, Pencil, Plus, Search, Trash2, XCircle } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import type { SharedPageProps } from '@/types';

interface EntityMeta {
    key: string;
    label: string;
    description: string;
}

interface ColumnMeta {
    key: string;
    label: string;
}

interface FieldMeta {
    label: string;
    type: 'text' | 'textarea' | 'url' | 'number' | 'boolean' | 'select';
    default: unknown;
    required: boolean;
    description: string;
    options: Array<{ value: string | number; label: string }>;
    meta: Record<string, unknown>;
}

interface MasterItem {
    id: number;
    updated_at: string | null;
    [key: string]: unknown;
}

interface Paginator {
    data: MasterItem[];
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
    entities: EntityMeta[];
    activeEntity: string;
    schema: {
        label: string;
        singular: string;
        description: string;
        columns: ColumnMeta[];
        fields: Record<string, FieldMeta>;
    };
    items: Paginator;
    filters: {
        q: string;
        status: string;
        per_page: number;
    };
    options: Record<string, Array<{ value: string | number; label: string }>>;
}>();

const page = usePage<SharedPageProps>();
const editingId = ref<number | null>(null);
const selectedIds = ref<number[]>([]);
const search = ref(props.filters.q);
const status = ref(props.filters.status);
const perPage = ref(props.filters.per_page);

function initialValues() {
    return Object.fromEntries(
        Object.entries(props.schema.fields).map(([key, field]) => [key, field.default]),
    );
}

const form = useForm<Record<string, any>>(initialValues());
const bulkForm = useForm({
    action: '',
    ids: [] as number[],
});

const allCurrentSelected = computed(() =>
    props.items.data.length > 0
    && props.items.data.every((item) => selectedIds.value.includes(item.id)),
);

function optionList(key: string, field: FieldMeta) {
    return props.options[key] ?? field.options ?? [];
}

function resetEditor() {
    editingId.value = null;
    form.clearErrors();
    form.defaults(initialValues());
    form.reset();
}

function edit(item: MasterItem) {
    editingId.value = item.id;
    form.clearErrors();

    for (const [key, field] of Object.entries(props.schema.fields)) {
        form[key] = item[key] ?? field.default;
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function submit() {
    if (editingId.value) {
        form.put(`/admin/master-data/${props.activeEntity}/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => resetEditor(),
        });
        return;
    }

    form.post(`/admin/master-data/${props.activeEntity}`, {
        preserveScroll: true,
        onSuccess: () => resetEditor(),
    });
}

function applyFilters() {
    router.get(
        `/admin/master-data/${props.activeEntity}`,
        {
            q: search.value || undefined,
            status: status.value,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearFilters() {
    search.value = '';
    status.value = 'all';
    perPage.value = 25;
    applyFilters();
}

function toggleAll() {
    if (allCurrentSelected.value) {
        selectedIds.value = selectedIds.value.filter(
            (id) => !props.items.data.some((item) => item.id === id),
        );
        return;
    }

    selectedIds.value = Array.from(
        new Set([...selectedIds.value, ...props.items.data.map((item) => item.id)]),
    );
}

function applyBulk() {
    if (!bulkForm.action || selectedIds.value.length === 0) return;

    if (bulkForm.action === 'delete' && !window.confirm('Hapus data yang dipilih? Data akan masuk soft delete.')) {
        return;
    }

    bulkForm.ids = [...selectedIds.value];
    bulkForm.post(`/admin/master-data/${props.activeEntity}/bulk`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkForm.reset();
        },
    });
}

function remove(item: MasterItem) {
    if (!window.confirm(`Hapus ${String(item.name || item.code || 'data ini')}?`)) {
        return;
    }

    router.delete(`/admin/master-data/${props.activeEntity}/${item.id}`, {
        preserveScroll: true,
    });
}

function displayValue(item: MasterItem, column: ColumnMeta) {
    if (column.key === 'is_active') return item.is_active ? 'Aktif' : 'Nonaktif';

    const value = item[column.key];

    if (value === null || value === undefined || value === '') return '—';

    return String(value);
}
</script>

<template>
    <Head :title="`Master Data — ${schema.label}`" />

    <AdminLayout>
        <div class="max-w-[1500px]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <LibraryBig class="size-5" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-primary">Perpustakaan</p>
                        <h1 class="mt-1 text-3xl font-semibold tracking-tight">Master Data</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Kelola data dasar yang akan dipakai katalog ebook secara konsisten.
                        </p>
                    </div>
                </div>

                <div class="text-sm text-muted-foreground">
                    {{ items.total }} data {{ schema.label.toLowerCase() }}
                </div>
            </div>

            <div v-if="page.props.flash.status" class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.status }}
            </div>

            <div class="mt-7 overflow-x-auto">
                <nav class="flex min-w-max gap-2">
                    <Link
                        v-for="entity in entities"
                        :key="entity.key"
                        :href="`/admin/master-data/${entity.key}`"
                        class="rounded-xl border px-4 py-3 text-sm transition-colors"
                        :class="entity.key === activeEntity
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-surface hover:bg-muted'"
                    >
                        {{ entity.label }}
                    </Link>
                </nav>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <section class="min-w-0 rounded-2xl border border-border bg-surface">
                    <div class="border-b border-border p-4 sm:p-5">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                            <form class="flex min-w-0 flex-1 gap-2" @submit.prevent="applyFilters">
                                <div class="flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-xl border border-border bg-background px-3">
                                    <Search class="size-4 shrink-0 text-muted-foreground" />
                                    <input
                                        v-model="search"
                                        type="search"
                                        class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                                        :placeholder="`Cari ${schema.label.toLowerCase()}...`"
                                    >
                                </div>
                                <Button type="submit" variant="secondary">Cari</Button>
                            </form>

                            <div class="flex flex-wrap gap-2">
                                <select
                                    v-model="status"
                                    class="min-h-11 rounded-xl border border-border bg-background px-3 text-sm"
                                    @change="applyFilters"
                                >
                                    <option value="all">Semua status</option>
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </select>

                                <select
                                    v-model="perPage"
                                    class="min-h-11 rounded-xl border border-border bg-background px-3 text-sm"
                                    @change="applyFilters"
                                >
                                    <option :value="10">10 / halaman</option>
                                    <option :value="25">25 / halaman</option>
                                    <option :value="50">50 / halaman</option>
                                    <option :value="100">100 / halaman</option>
                                </select>

                                <button type="button" class="px-3 text-sm text-muted-foreground hover:text-foreground" @click="clearFilters">
                                    Reset
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row sm:items-center">
                            <select
                                v-model="bulkForm.action"
                                class="min-h-10 rounded-xl border border-border bg-background px-3 text-sm"
                            >
                                <option value="">Bulk action...</option>
                                <option value="activate">Aktifkan</option>
                                <option value="deactivate">Nonaktifkan</option>
                                <option value="delete">Hapus</option>
                            </select>
                            <Button
                                type="button"
                                variant="secondary"
                                :disabled="!bulkForm.action || selectedIds.length === 0 || bulkForm.processing"
                                @click="applyBulk"
                            >
                                Terapkan ke {{ selectedIds.length }} data
                            </Button>
                            <p v-if="bulkForm.errors.ids" class="text-sm text-red-600">{{ bulkForm.errors.ids }}</p>
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
                                            aria-label="Pilih semua"
                                            @change="toggleAll"
                                        >
                                    </th>
                                    <th v-for="column in schema.columns" :key="column.key" class="whitespace-nowrap px-4 py-3">
                                        {{ column.label }}
                                    </th>
                                    <th class="w-28 px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="item in items.data" :key="item.id" class="hover:bg-muted/30">
                                    <td class="px-4 py-4">
                                        <input
                                            v-model="selectedIds"
                                            type="checkbox"
                                            :value="item.id"
                                            class="size-4 rounded border-border"
                                            :aria-label="`Pilih ${String(item.name || item.code || item.id)}`"
                                        >
                                    </td>
                                    <td
                                        v-for="column in schema.columns"
                                        :key="column.key"
                                        class="max-w-[280px] px-4 py-4"
                                    >
                                        <span
                                            v-if="column.key === 'is_active'"
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="item.is_active
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-slate-100 text-slate-600'"
                                        >
                                            {{ displayValue(item, column) }}
                                        </span>
                                        <span v-else class="block truncate" :title="displayValue(item, column)">
                                            {{ displayValue(item, column) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex justify-end gap-1">
                                            <button
                                                type="button"
                                                class="rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                title="Edit"
                                                @click="edit(item)"
                                            >
                                                <Pencil class="size-4" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg p-2 text-muted-foreground hover:bg-red-50 hover:text-red-600"
                                                title="Hapus"
                                                @click="remove(item)"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="!items.data.length">
                                    <td :colspan="schema.columns.length + 2" class="px-4 py-14 text-center">
                                        <div class="mx-auto max-w-sm">
                                            <LibraryBig class="mx-auto size-7 text-muted-foreground" />
                                            <p class="mt-3 font-medium">Belum ada data</p>
                                            <p class="mt-1 text-sm text-muted-foreground">
                                                Tambahkan {{ schema.singular.toLowerCase() }} pertama melalui form di samping.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-border px-4 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-muted-foreground">
                            Menampilkan {{ items.from || 0 }}–{{ items.to || 0 }} dari {{ items.total }}
                        </span>
                        <div class="flex gap-2">
                            <Link
                                v-if="items.prev_page_url"
                                :href="items.prev_page_url"
                                preserve-scroll
                                class="rounded-lg border border-border px-3 py-2 hover:bg-muted"
                            >
                                Sebelumnya
                            </Link>
                            <span class="rounded-lg bg-muted px-3 py-2">
                                {{ items.current_page }} / {{ items.last_page }}
                            </span>
                            <Link
                                v-if="items.next_page_url"
                                :href="items.next_page_url"
                                preserve-scroll
                                class="rounded-lg border border-border px-3 py-2 hover:bg-muted"
                            >
                                Berikutnya
                            </Link>
                        </div>
                    </div>
                </section>

                <aside class="h-fit rounded-2xl border border-border bg-surface xl:sticky xl:top-6">
                    <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-5">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-primary">
                                {{ editingId ? 'Edit data' : 'Tambah data' }}
                            </p>
                            <h2 class="mt-1 text-xl font-semibold">
                                {{ editingId ? `Edit ${schema.singular}` : `${schema.singular} baru` }}
                            </h2>
                            <p class="mt-1 text-sm leading-5 text-muted-foreground">{{ schema.description }}</p>
                        </div>
                        <button
                            v-if="editingId"
                            type="button"
                            class="rounded-lg p-2 text-muted-foreground hover:bg-muted"
                            title="Batal edit"
                            @click="resetEditor"
                        >
                            <XCircle class="size-5" />
                        </button>
                    </div>

                    <form class="space-y-5 p-5" @submit.prevent="submit">
                        <div v-for="(field, key) in schema.fields" :key="key">
                            <label :for="String(key)" class="mb-2 block text-sm font-medium">
                                {{ field.label }}
                                <span v-if="field.required" class="text-red-500">*</span>
                            </label>

                            <label
                                v-if="field.type === 'boolean'"
                                class="flex min-h-11 items-center gap-3 rounded-xl border border-border bg-background px-4 text-sm"
                            >
                                <input v-model="form[String(key)]" type="checkbox" class="size-4 rounded border-border">
                                <span class="inline-flex items-center gap-2">
                                    <CheckCircle2 v-if="form[String(key)]" class="size-4 text-emerald-600" />
                                    <XCircle v-else class="size-4 text-muted-foreground" />
                                    {{ form[String(key)] ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </label>

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
                                <option :value="null">— Tidak ada —</option>
                                <option
                                    v-for="option in optionList(String(key), field)"
                                    :key="String(option.value)"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>

                            <input
                                v-else
                                :id="String(key)"
                                v-model="form[String(key)]"
                                :type="field.type"
                                :min="field.type === 'number' ? Number(field.meta.min) : undefined"
                                :max="field.type === 'number' ? Number(field.meta.max) : undefined"
                                class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-primary/20"
                            >

                            <p v-if="field.description" class="mt-1.5 text-xs leading-5 text-muted-foreground">
                                {{ field.description }}
                            </p>
                            <p v-if="form.errors[String(key)]" class="mt-2 text-sm text-red-600">
                                {{ form.errors[String(key)] }}
                            </p>
                        </div>

                        <div class="flex gap-2 border-t border-border pt-5">
                            <Button class="flex-1" :disabled="form.processing">
                                <Pencil v-if="editingId" class="size-4" />
                                <Plus v-else class="size-4" />
                                {{ form.processing ? 'Menyimpan...' : (editingId ? 'Simpan perubahan' : 'Tambah data') }}
                            </Button>
                            <Button v-if="editingId" type="button" variant="secondary" @click="resetEditor">
                                Batal
                            </Button>
                        </div>
                    </form>
                </aside>
            </div>
        </div>
    </AdminLayout>
</template>
