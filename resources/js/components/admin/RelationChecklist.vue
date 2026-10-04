<script setup lang="ts">
import { computed, ref } from 'vue';
import { Search } from '@lucide/vue';

interface OptionItem {
    value: number;
    label: string;
    active?: boolean;
}

const props = defineProps<{
    label: string;
    modelValue: number[];
    options: OptionItem[];
    description?: string;
    searchPlaceholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const search = ref('');

const filtered = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase('id-ID');

    if (!needle) return props.options;

    return props.options.filter((option) =>
        option.label.toLocaleLowerCase('id-ID').includes(needle),
    );
});

function toggle(value: number) {
    if (props.modelValue.includes(value)) {
        emit(
            'update:modelValue',
            props.modelValue.filter((item) => item !== value),
        );
        return;
    }

    emit('update:modelValue', [...props.modelValue, value]);
}
</script>

<template>
    <div>
        <div class="mb-2">
            <p class="text-sm font-medium">{{ label }}</p>
            <p v-if="description" class="mt-1 text-xs leading-5 text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border border-border bg-background">
            <div class="flex min-h-10 items-center gap-2 border-b border-border px-3">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    v-model="search"
                    type="search"
                    class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                    :placeholder="searchPlaceholder || 'Cari...'"
                >
            </div>

            <div class="max-h-56 overflow-y-auto p-2">
                <label
                    v-for="option in filtered"
                    :key="option.value"
                    class="flex cursor-pointer items-start gap-3 rounded-lg px-2.5 py-2 text-sm hover:bg-muted"
                >
                    <input
                        type="checkbox"
                        class="mt-0.5 size-4 rounded border-border"
                        :checked="modelValue.includes(option.value)"
                        @change="toggle(option.value)"
                    >
                    <span class="min-w-0 flex-1">
                        <span class="block truncate">{{ option.label }}</span>
                        <span v-if="option.active === false" class="mt-0.5 block text-xs text-amber-700">
                            Nonaktif — relasi lama tetap dipertahankan
                        </span>
                    </span>
                </label>

                <p v-if="!filtered.length" class="px-3 py-6 text-center text-sm text-muted-foreground">
                    Tidak ada pilihan yang cocok.
                </p>
            </div>

            <div class="border-t border-border px-3 py-2 text-xs text-muted-foreground">
                {{ modelValue.length }} dipilih
            </div>
        </div>
    </div>
</template>
