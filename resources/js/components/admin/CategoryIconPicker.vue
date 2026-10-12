<script setup lang="ts">
import { computed, ref } from 'vue';
import { Search } from '@lucide/vue';
import CategoryIcon from '@/components/public/CategoryIcon.vue';

interface IconOption {
    value: string | number;
    label: string;
}

const model = defineModel<string>({ required: true });

const props = defineProps<{
    options: IconOption[];
}>();

const query = ref('');

const filteredOptions = computed(() => {
    const needle = query.value.trim().toLocaleLowerCase('id-ID');

    if (!needle) return props.options;

    return props.options.filter((option) => {
        const value = String(option.value).toLocaleLowerCase('id-ID');
        const label = option.label.toLocaleLowerCase('id-ID');

        return value.includes(needle) || label.includes(needle);
    });
});

const selected = computed(() =>
    props.options.find((option) => String(option.value) === model.value),
);
</script>

<template>
    <div class="grid gap-2.5">
        <div class="flex min-h-11 items-center gap-3 rounded-[var(--radius-md)] border border-line bg-surface px-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-sm)] bg-brand-soft text-brand">
                <CategoryIcon :name="model" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold text-ink">
                    {{ selected?.label || 'Ikon kategori' }}
                </p>
                <p class="truncate text-[10px] text-ink-faint">{{ model || 'BookOpen' }}</p>
            </div>
        </div>

        <div class="ui-control ui-focus-ring flex min-h-10 items-center px-3">
            <Search class="size-3.5 shrink-0 text-ink-faint" />
            <input
                v-model="query"
                type="search"
                class="min-w-0 flex-1 bg-transparent px-2 text-xs text-ink outline-none placeholder:text-ink-faint"
                placeholder="Cari ikon…"
                aria-label="Cari ikon kategori"
            >
        </div>

        <div class="grid max-h-56 grid-cols-4 gap-1.5 overflow-y-auto rounded-[var(--radius-md)] border border-line bg-surface-subtle p-2 sm:grid-cols-5">
            <button
                v-for="option in filteredOptions"
                :key="String(option.value)"
                type="button"
                class="group grid min-h-16 place-items-center rounded-[var(--radius-md)] border px-1.5 py-2 text-center transition-colors"
                :class="String(option.value) === model
                    ? 'border-brand bg-brand-soft text-brand'
                    : 'border-transparent bg-surface text-ink-soft hover:border-line-strong hover:text-ink'"
                :aria-pressed="String(option.value) === model"
                :title="option.label"
                @click="model = String(option.value)"
            >
                <CategoryIcon :name="String(option.value)" size-class="size-4.5" />
                <span class="mt-1 line-clamp-2 text-[9px] font-semibold leading-3">{{ option.label }}</span>
            </button>

            <p
                v-if="!filteredOptions.length"
                class="col-span-full px-2 py-5 text-center text-[11px] text-ink-faint"
            >
                Ikon tidak ditemukan.
            </p>
        </div>
    </div>
</template>
