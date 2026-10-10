<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { BookOpen, Construction, Mail, Phone } from '@lucide/vue';
import type { SharedPageProps } from '@/types';

defineProps<{
    message: string;
    contactText: string | null;
}>();

const page = usePage<SharedPageProps>();
const siteName = computed(() => String(page.props.site.general.site_name || 'Digital Library'));
const logoUrl = computed(() => page.props.site.general.logo_url ? String(page.props.site.general.logo_url) : null);

const themeStyle = computed(() => {
    const brand = String(page.props.site.appearance.primary_color || '#3157D5');
    const canvas = String(page.props.site.appearance.background_color || '#F7F6F2');

    return {
        '--brand': brand,
        '--primary': brand,
        '--canvas': canvas,
        '--background': canvas,
    };
});

const phone = computed(() => String(page.props.site.general.phone || ''));
const email = computed(() => String(page.props.site.general.email || ''));
</script>

<template>
    <Head title="Maintenance" />

    <main
        class="flex min-h-dvh items-center justify-center bg-canvas px-4 py-10 text-ink sm:px-6"
        :style="themeStyle"
    >
        <section class="w-full max-w-xl">
            <div class="flex items-center justify-center gap-2.5">
                <span class="grid size-9 place-items-center overflow-hidden rounded-[var(--radius-md)] bg-brand text-brand-foreground shadow-sm">
                    <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                    <BookOpen v-else class="size-4" />
                </span>
                <span class="max-w-72 truncate text-sm font-semibold tracking-tight">{{ siteName }}</span>
            </div>

            <div class="mt-7 rounded-[var(--radius-xl)] border border-line bg-surface p-5 text-center shadow-[var(--shadow-float)] sm:p-7">
                <span class="mx-auto grid size-11 place-items-center rounded-[var(--radius-lg)] bg-warning-soft text-warning">
                    <Construction class="size-5" />
                </span>

                <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.12em] text-warning">Maintenance</p>
                <h1 class="mt-1.5 text-balance text-2xl font-semibold tracking-[-0.025em] sm:text-3xl">
                    Sedang dalam pemeliharaan
                </h1>

                <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-ink-soft">
                    {{ message }}
                </p>

                <p v-if="contactText" class="mt-5 text-xs font-semibold text-ink">
                    {{ contactText }}
                </p>

                <div v-if="phone || email" class="mt-5 flex flex-wrap justify-center gap-2 border-t border-line pt-4">
                    <a
                        v-if="phone"
                        :href="'tel:' + phone"
                        class="inline-flex min-h-10 items-center gap-2 rounded-[var(--radius-md)] border border-line px-3 text-xs font-semibold text-ink-soft hover:bg-surface-subtle hover:text-ink"
                    >
                        <Phone class="size-3.5" />
                        {{ phone }}
                    </a>
                    <a
                        v-if="email"
                        :href="'mailto:' + email"
                        class="inline-flex min-h-10 items-center gap-2 rounded-[var(--radius-md)] border border-line px-3 text-xs font-semibold text-ink-soft hover:bg-surface-subtle hover:text-ink"
                    >
                        <Mail class="size-3.5" />
                        {{ email }}
                    </a>
                </div>
            </div>

            <p class="mt-4 text-center text-[10px] text-ink-faint">
                Admin tetap dapat mengakses area administrasi selama maintenance publik.
            </p>
        </section>
    </main>
</template>
