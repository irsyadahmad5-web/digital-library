<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ExternalLink,
    Mail,
    MapPin,
    Menu,
    Phone,
    Search,
    X,
} from '@lucide/vue';
import PublicSearchForm from '@/components/public/PublicSearchForm.vue';
import PwaStatus from '@/components/public/PwaStatus.vue';
import { IconButton } from '@/components/ui/icon-button';
import { SheetShell } from '@/components/ui/sheet';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const site = page.props.site;
const mobileOpen = ref(false);
const searchOpen = ref(false);

const siteName = computed(() => String(site.general.site_name || 'Digital Library'));
const logoUrl = computed(() => site.general.logo_url ? String(site.general.logo_url) : null);
const faviconUrl = computed(() => site.general.favicon_url ? String(site.general.favicon_url) : null);
const tagline = computed(() => String(site.general.tagline || ''));
const address = computed(() => String(site.general.address || ''));
const phone = computed(() => String(site.general.phone || ''));
const email = computed(() => String(site.general.email || ''));
const currentPath = computed(() => page.url.split('?')[0] || '/');

const currentSearch = computed(() => {
    const queryString = page.url.split('?')[1] ?? '';

    return new URLSearchParams(queryString).get('q') ?? '';
});

const themeStyle = computed(() => {
    const brand = String(site.appearance.primary_color || '#3157D5');
    const canvas = String(site.appearance.background_color || '#F7F6F2');
    const width = `${Number(site.appearance.content_max_width || 1280)}px`;

    return {
        '--brand': brand,
        '--primary': brand,
        '--canvas': canvas,
        '--background': canvas,
        '--content-max': width,
        '--content-max-width': width,
    };
});

const primaryNavigation = [
    { label: 'Katalog', href: '/library', prefixes: ['/library', '/search', '/book/'] },
    { label: 'Kategori', href: '/categories', prefixes: ['/categories', '/category/'] },
    { label: 'Koleksi', href: '/collections', prefixes: ['/collections', '/collection/'] },
    { label: 'Penulis', href: '/authors', prefixes: ['/authors', '/author/'] },
    { label: 'Tentang', href: '/about', prefixes: ['/about'] },
];

const secondaryNavigation = [
    { label: 'Penerbit', href: '/publishers', prefixes: ['/publishers', '/publisher/'] },
    { label: 'Kontak', href: '/contact', prefixes: ['/contact'] },
];

function isActive(prefixes: string[]) {
    return prefixes.some((prefix) => currentPath.value === prefix || currentPath.value.startsWith(prefix));
}

function closeOverlays() {
    mobileOpen.value = false;
    searchOpen.value = false;
}

function toggleSearch() {
    searchOpen.value = !searchOpen.value;

    if (searchOpen.value) mobileOpen.value = false;
}

const removeNavigateListener = router.on('navigate', closeOverlays);

onBeforeUnmount(() => {
    removeNavigateListener();
});
</script>

<template>
    <div class="safe-x min-h-screen bg-canvas text-ink" :style="themeStyle">
        <Head>
            <link v-if="faviconUrl" head-key="favicon" rel="icon" :href="faviconUrl">
        </Head>

        <header class="safe-top sticky top-0 z-40 border-b border-line/80 bg-surface/96 backdrop-blur-md">
            <div
                class="mx-auto flex min-h-[72px] items-center gap-3 px-[var(--page-gutter)]"
                style="max-width: var(--content-max-width)"
            >
                <Link href="/" class="flex min-w-0 shrink-0 items-center gap-2.5" aria-label="Beranda">
                    <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-[var(--radius-md)] bg-brand text-brand-foreground shadow-sm">
                        <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                        <BookOpen v-else class="size-[17px]" />
                    </span>
                    <span class="max-w-44 truncate text-[15px] font-semibold tracking-tight sm:max-w-56">{{ siteName }}</span>
                </Link>

                <nav class="ml-2 hidden min-w-0 items-center gap-0.5 xl:flex" aria-label="Navigasi utama">
                    <Link
                        v-for="item in primaryNavigation"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-[var(--radius-md)] px-3 py-2 text-[13px] font-medium transition-colors"
                        :class="isActive(item.prefixes)
                            ? 'bg-brand-soft text-brand'
                            : 'text-ink-soft hover:bg-surface-subtle hover:text-ink'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ml-auto hidden w-[min(31vw,23rem)] lg:block">
                    <PublicSearchForm
                        :initial-query="currentSearch"
                        placeholder="Cari judul, penulis, topik…"
                        compact
                        :show-submit="false"
                    />
                </div>

                <div class="ml-auto flex items-center gap-1.5 lg:ml-0">
                    <IconButton
                        class="lg:hidden"
                        :label="searchOpen ? 'Tutup pencarian' : 'Cari ebook'"
                        variant="quiet"
                        :aria-expanded="searchOpen"
                        @click="toggleSearch"
                    >
                        <X v-if="searchOpen" class="size-[18px]" />
                        <Search v-else class="size-[18px]" />
                    </IconButton>

                    <IconButton
                        class="xl:hidden"
                        label="Buka navigasi"
                        variant="quiet"
                        :aria-expanded="mobileOpen"
                        @click="mobileOpen = true"
                    >
                        <Menu class="size-5" />
                    </IconButton>
                </div>
            </div>

            <div v-if="searchOpen" class="border-t border-line bg-surface lg:hidden">
                <div class="mx-auto px-[var(--page-gutter)] py-3" style="max-width: var(--content-max-width)">
                    <PublicSearchForm
                        :initial-query="currentSearch"
                        placeholder="Cari judul, penulis, topik, kategori, atau ISBN…"
                        compact
                        auto-focus
                    />
                </div>
            </div>
        </header>

        <SheetShell
            :open="mobileOpen"
            side="right"
            title="Jelajahi Ladunni"
            :description="tagline || 'Temukan dan baca koleksi perpustakaan digital.'"
            @update:open="mobileOpen = $event"
        >
            <div class="grid gap-6">
                <PublicSearchForm
                    :initial-query="currentSearch"
                    placeholder="Cari ebook…"
                    compact
                />

                <nav class="grid gap-1" aria-label="Navigasi mobile">
                    <Link
                        href="/"
                        class="flex min-h-11 items-center rounded-[var(--radius-md)] px-3 text-sm font-medium"
                        :class="currentPath === '/' ? 'bg-brand-soft text-brand' : 'text-ink hover:bg-surface-subtle'"
                        @click="mobileOpen = false"
                    >
                        Beranda
                    </Link>
                    <Link
                        v-for="item in primaryNavigation"
                        :key="item.href"
                        :href="item.href"
                        class="flex min-h-11 items-center rounded-[var(--radius-md)] px-3 text-sm font-medium"
                        :class="isActive(item.prefixes) ? 'bg-brand-soft text-brand' : 'text-ink hover:bg-surface-subtle'"
                        @click="mobileOpen = false"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="border-t border-line pt-4">
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-ink-faint">Lainnya</p>
                    <nav class="mt-2 grid gap-1">
                        <Link
                            v-for="item in secondaryNavigation"
                            :key="item.href"
                            :href="item.href"
                            class="flex min-h-11 items-center rounded-[var(--radius-md)] px-3 text-sm text-ink-soft hover:bg-surface-subtle hover:text-ink"
                            :class="isActive(item.prefixes) ? 'bg-surface-subtle font-medium text-ink' : ''"
                            @click="mobileOpen = false"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>
            </div>
        </SheetShell>

        <main>
            <slot />
        </main>

        <footer class="safe-bottom mt-14 border-t border-line bg-surface sm:mt-16">
            <div
                class="mx-auto grid gap-8 px-[var(--page-gutter)] py-9 sm:grid-cols-2 lg:grid-cols-[1.4fr_.8fr_.8fr_1fr] lg:gap-10"
                style="max-width: var(--content-max-width)"
            >
                <div class="sm:col-span-2 lg:col-span-1">
                    <Link href="/" class="inline-flex items-center gap-2.5 font-semibold tracking-tight">
                        <span class="grid size-9 place-items-center overflow-hidden rounded-[var(--radius-md)] bg-brand text-brand-foreground">
                            <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                            <BookOpen v-else class="size-4" />
                        </span>
                        <span>{{ siteName }}</span>
                    </Link>
                    <p v-if="tagline" class="mt-3 max-w-md text-sm leading-6 text-ink-soft">{{ tagline }}</p>
                    <Link href="/library" class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-brand hover:underline">
                        Jelajahi katalog
                        <ExternalLink class="size-3.5" />
                    </Link>
                </div>

                <div>
                    <p class="text-xs font-semibold text-ink">Jelajahi</p>
                    <div class="mt-3 grid gap-2 text-sm text-ink-soft">
                        <Link href="/library" class="hover:text-ink">Katalog</Link>
                        <Link href="/categories" class="hover:text-ink">Kategori</Link>
                        <Link href="/collections" class="hover:text-ink">Koleksi</Link>
                        <Link href="/authors" class="hover:text-ink">Penulis</Link>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold text-ink">Referensi</p>
                    <div class="mt-3 grid gap-2 text-sm text-ink-soft">
                        <Link href="/publishers" class="hover:text-ink">Penerbit</Link>
                        <Link href="/about" class="hover:text-ink">Tentang</Link>
                        <Link href="/contact" class="hover:text-ink">Kontak</Link>
                    </div>
                </div>

                <div v-if="address || phone || email">
                    <p class="text-xs font-semibold text-ink">Kontak</p>
                    <div class="mt-3 grid gap-2.5 text-sm leading-5 text-ink-soft">
                        <span v-if="address" class="flex items-start gap-2">
                            <MapPin class="mt-0.5 size-4 shrink-0" />
                            <span>{{ address }}</span>
                        </span>
                        <a v-if="phone" :href="`tel:${phone}`" class="flex items-center gap-2 hover:text-ink">
                            <Phone class="size-4 shrink-0" />
                            <span>{{ phone }}</span>
                        </a>
                        <a v-if="email" :href="`mailto:${email}`" class="flex items-center gap-2 break-all hover:text-ink">
                            <Mail class="size-4 shrink-0" />
                            <span>{{ email }}</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-t border-line">
                <div
                    class="mx-auto flex flex-col gap-1.5 px-[var(--page-gutter)] py-4 text-[11px] text-ink-faint sm:flex-row sm:items-center sm:justify-between"
                    style="max-width: var(--content-max-width)"
                >
                    <span>© {{ new Date().getFullYear() }} {{ siteName }}</span>
                    <span>Perpustakaan digital publik · Baca tanpa login</span>
                </div>
            </div>
        </footer>

        <PwaStatus />
    </div>
</template>
