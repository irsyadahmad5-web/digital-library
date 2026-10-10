<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    LibraryBig,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    XCircle,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
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
const pendingDelete = ref<MasterItem | null>(null);
const deleteBusy = ref(false);
const bulkDeleteConfirmOpen = ref(false);

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
        form.put('/admin/master-data/' + props.activeEntity + '/' + editingId.value, {
            preserveScroll: true,
            onSuccess: () => resetEditor(),
        });
        return;
    }

    form.post('/admin/master-data/' + props.activeEntity, {
        preserveScroll: true,
        onSuccess: () => resetEditor(),
    });
}

function applyFilters() {
    router.get(
        '/admin/master-data/' + props.activeEntity,
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

function submitBulk() {
    if (!bulkForm.action || selectedIds.value.length === 0) return;

    bulkForm.ids = [...selectedIds.value];
    bulkForm.post('/admin/master-data/' + props.activeEntity + '/bulk', {
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

function itemName(item: MasterItem) {
    return String(item.name || item.code || props.schema.singular);
}

function requestRemove(item: MasterItem) {
    pendingDelete.value = item;
}

function confirmRemove() {
    const item = pendingDelete.value;
    if (!item || deleteBusy.value) return;

    deleteBusy.value = true;

    router.delete('/admin/master-data/' + props.activeEntity + '/' + item.id, {
        preserveScroll: true,
        onFinish: () => {
            deleteBusy.value = false;
            pendingDelete.value = null;
        },
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
    <Head :title="'Master Data — ' + schema.label" />

    <AdminLayout>
        <div class="grid gap-5">
            <PageHeader
                eyebrow="Library"
                title="Master Data"
                :description="'Kelola ' + schema.label.toLowerCase() + ' dan data referensi katalog secara konsisten. ' + items.total + ' data tersedia.'"
            />

            <Alert v-if="page.props.flash.status" tone="success" :title="page.props.flash.status" />

            <div class="-mx-1 overflow-x-auto px-1 pb-1">
                <nav class="flex min-w-max gap-1" aria-label="Jenis master data">
                    <Link
                        v-for="entity in entities"
                        :key="entity.key"
                        :href="'/admin/master-data/' + entity.key"
                        class="min-h-9 rounded-[var(--radius-md)] px-3 py-2 text-xs font-semibold transition-colors"
                        :class="entity.key === activeEntity
                            ? 'bg-brand text-brand-foreground'
                            : 'border border-line bg-surface text-ink-soft hover:bg-surface-subtle hover:text-ink'"
                        :aria-current="entity.key === activeEntity ? 'page' : undefined"
                    >
                        {{ entity.label }}
                    </Link>
                </nav>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                <section class="min-w-0 overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface">
                    <div class="border-b border-line p-3.5 sm:p-4">
                        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
                            <form class="flex min-w-0 flex-1 gap-2" @submit.prevent="applyFilters">
                                <div class="ui-control ui-focus-ring flex min-w-0 flex-1 items-center px-3">
                                    <Search class="size-4 shrink-0 text-ink-faint" />
                                    <input
                                        v-model="search"
                                        type="search"
                                        class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none placeholder:text-ink-faint"
                                        :placeholder="'Cari ' + schema.label.toLowerCase() + '…'"
                                    >
                                </div>
                                <Button type="submit" size="small" variant="secondary">Cari</Button>
                            </form>

                            <div class="flex flex-wrap gap-2">
                                <Select v-model="status" class="w-auto min-w-32" @change="applyFilters">
                                    <option value="all">Semua status</option>
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Nonaktif</option>
                                </Select>

                                <Select v-model="perPage" class="w-auto min-w-32" @change="applyFilters">
                                    <option :value="10">10 / halaman</option>
                                    <option :value="25">25 / halaman</option>
                                    <option :value="50">50 / halaman</option>
                                    <option :value="100">100 / halaman</option>
                                </Select>

                                <Button type="button" size="small" variant="quiet" @click="clearFilters">
                                    <RotateCcw class="size-3.5" />
                                    Reset
                                </Button>
                            </div>
                        </div>

                        <div
                            v-if="selectedIds.length"
                            class="mt-3 flex flex-col gap-2 border-t border-line pt-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-xs font-semibold text-ink">{{ selectedIds.length }} data dipilih</p>
                            <div class="flex flex-wrap gap-2">
                                <Select v-model="bulkForm.action" class="w-auto min-w-40">
                                    <option value="">Bulk action…</option>
                                    <option value="activate">Aktifkan</option>
                                    <option value="deactivate">Nonaktifkan</option>
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
                            <p v-if="bulkForm.errors.ids" class="text-xs text-danger">{{ bulkForm.errors.ids }}</p>
                        </div>
                    </div>

                    <div v-if="items.data.length" class="hidden overflow-x-auto md:block">
                        <table class="min-w-full text-left text-xs">
                            <thead class="border-b border-line bg-surface-subtle text-[10px] uppercase tracking-[0.06em] text-ink-faint">
                                <tr>
                                    <th class="w-10 px-3 py-3">
                                        <input
                                            type="checkbox"
                                            :checked="allCurrentSelected"
                                            class="size-4 rounded border-line"
                                            aria-label="Pilih semua"
                                            @change="toggleAll"
                                        >
                                    </th>
                                    <th v-for="column in schema.columns" :key="column.key" class="whitespace-nowrap px-3 py-3 font-semibold">
                                        {{ column.label }}
                                    </th>
                                    <th class="w-24 px-3 py-3 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <tr v-for="item in items.data" :key="item.id" class="transition-colors hover:bg-surface-subtle/60">
                                    <td class="px-3 py-3">
                                        <input
                                            v-model="selectedIds"
                                            type="checkbox"
                                            :value="item.id"
                                            class="size-4 rounded border-line"
                                            :aria-label="'Pilih ' + itemName(item)"
                                        >
                                    </td>
                                    <td
                                        v-for="column in schema.columns"
                                        :key="column.key"
                                        class="max-w-[280px] px-3 py-3"
                                    >
                                        <Badge
                                            v-if="column.key === 'is_active'"
                                            :tone="item.is_active ? 'success' : 'neutral'"
                                        >
                                            {{ displayValue(item, column) }}
                                        </Badge>
                                        <span v-else class="block truncate text-ink" :title="displayValue(item, column)">
                                            {{ displayValue(item, column) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex justify-end gap-1">
                                            <button
                                                type="button"
                                                class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink"
                                                :aria-label="'Edit ' + itemName(item)"
                                                @click="edit(item)"
                                            >
                                                <Pencil class="size-4" />
                                            </button>
                                            <button
                                                type="button"
                                                class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-danger-soft hover:text-danger"
                                                :aria-label="'Hapus ' + itemName(item)"
                                                @click="requestRemove(item)"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="items.data.length" class="divide-y divide-line md:hidden">
                        <article v-for="item in items.data" :key="item.id" class="p-4">
                            <div class="flex items-start gap-3">
                                <input
                                    v-model="selectedIds"
                                    type="checkbox"
                                    :value="item.id"
                                    class="mt-1 size-4 shrink-0 rounded border-line"
                                    :aria-label="'Pilih ' + itemName(item)"
                                >

                                <dl class="min-w-0 flex-1 grid gap-2">
                                    <div
                                        v-for="column in schema.columns"
                                        :key="column.key"
                                        class="grid grid-cols-[100px_minmax(0,1fr)] gap-3 text-xs"
                                    >
                                        <dt class="text-ink-faint">{{ column.label }}</dt>
                                        <dd class="min-w-0 text-ink">
                                            <Badge
                                                v-if="column.key === 'is_active'"
                                                :tone="item.is_active ? 'success' : 'neutral'"
                                            >
                                                {{ displayValue(item, column) }}
                                            </Badge>
                                            <span v-else class="block truncate">{{ displayValue(item, column) }}</span>
                                        </dd>
                                    </div>
                                </dl>

                                <div class="flex shrink-0 flex-col gap-1">
                                    <button
                                        type="button"
                                        class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink"
                                        :aria-label="'Edit ' + itemName(item)"
                                        @click="edit(item)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="grid size-9 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-danger-soft hover:text-danger"
                                        :aria-label="'Hapus ' + itemName(item)"
                                        @click="requestRemove(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>

                    <EmptyState
                        v-if="!items.data.length"
                        title="Belum ada data"
                        :description="'Tambahkan ' + schema.singular.toLowerCase() + ' pertama melalui form editor.'"
                    >
                        <template #icon><LibraryBig class="size-5" /></template>
                    </EmptyState>

                    <div class="flex flex-col gap-3 border-t border-line px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-ink-soft">{{ items.from || 0 }}–{{ items.to || 0 }} dari {{ items.total }}</span>
                        <div v-if="items.last_page > 1" class="flex items-center gap-1.5">
                            <Button v-if="items.prev_page_url" as-child size="small" variant="secondary">
                                <Link :href="items.prev_page_url" preserve-scroll>Sebelumnya</Link>
                            </Button>
                            <span class="min-w-16 px-2 text-center font-semibold tabular-nums text-ink-soft">
                                {{ items.current_page }} / {{ items.last_page }}
                            </span>
                            <Button v-if="items.next_page_url" as-child size="small" variant="secondary">
                                <Link :href="items.next_page_url" preserve-scroll>Berikutnya</Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <aside class="h-fit rounded-[var(--radius-lg)] border border-line bg-surface xl:sticky xl:top-20">
                    <div class="flex items-start justify-between gap-4 border-b border-line px-4 py-3.5">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-brand">
                                {{ editingId ? 'Edit data' : 'Tambah data' }}
                            </p>
                            <h2 class="mt-1 text-base font-semibold text-ink">
                                {{ editingId ? 'Edit ' + schema.singular : schema.singular + ' baru' }}
                            </h2>
                            <p class="mt-1 text-xs leading-5 text-ink-soft">{{ schema.description }}</p>
                        </div>
                        <button
                            v-if="editingId"
                            type="button"
                            class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink"
                            aria-label="Batal edit"
                            @click="resetEditor"
                        >
                            <XCircle class="size-4" />
                        </button>
                    </div>

                    <form class="grid gap-4 p-4" @submit.prevent="submit">
                        <div v-for="(field, key) in schema.fields" :key="key" class="grid gap-1.5">
                            <label :for="String(key)" class="text-xs font-semibold text-ink">
                                {{ field.label }}
                                <span v-if="field.required" class="text-danger">*</span>
                            </label>

                            <div
                                v-if="field.type === 'boolean'"
                                class="flex items-center justify-between gap-4 rounded-[var(--radius-md)] border border-line px-3 py-3"
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
                                <option :value="null">— Tidak ada —</option>
                                <option
                                    v-for="option in optionList(String(key), field)"
                                    :key="String(option.value)"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </Select>

                            <input
                                v-else
                                :id="String(key)"
                                v-model="form[String(key)]"
                                :type="field.type"
                                :min="field.type === 'number' ? Number(field.meta.min) : undefined"
                                :max="field.type === 'number' ? Number(field.meta.max) : undefined"
                                class="ui-control ui-focus-ring w-full px-3 text-sm"
                            >

                            <p v-if="field.description" class="text-[11px] leading-5 text-ink-faint">
                                {{ field.description }}
                            </p>
                            <p v-if="form.errors[String(key)]" class="text-xs text-danger">
                                {{ form.errors[String(key)] }}
                            </p>
                        </div>

                        <div class="flex gap-2 border-t border-line pt-4">
                            <Button class="flex-1" :disabled="form.processing">
                                <Pencil v-if="editingId" class="size-4" />
                                <Plus v-else class="size-4" />
                                {{ form.processing ? 'Menyimpan…' : (editingId ? 'Simpan perubahan' : 'Tambah data') }}
                            </Button>
                            <Button v-if="editingId" type="button" variant="secondary" @click="resetEditor">
                                Batal
                            </Button>
                        </div>
                    </form>
                </aside>
            </div>
        </div>

        <ConfirmDialog
            :open="Boolean(pendingDelete)"
            :title="'Hapus ' + schema.singular.toLowerCase() + '?'"
            :description="pendingDelete ? '“' + itemName(pendingDelete) + '” akan masuk soft delete.' : ''"
            confirm-label="Hapus"
            destructive
            :busy="deleteBusy"
            @update:open="pendingDelete = $event ? pendingDelete : null"
            @confirm="confirmRemove"
        />

        <ConfirmDialog
            :open="bulkDeleteConfirmOpen"
            title="Hapus data terpilih?"
            :description="selectedIds.length + ' data akan masuk soft delete.'"
            confirm-label="Hapus yang dipilih"
            destructive
            :busy="bulkForm.processing"
            @update:open="bulkDeleteConfirmOpen = $event"
            @confirm="submitBulk"
        />
    </AdminLayout>
</template>
