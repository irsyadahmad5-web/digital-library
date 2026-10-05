<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Mail,
    MapPin,
    Menu,
    Phone,
    Search,
    X,
} from '@lucide/vue';
import PublicSearchForm from '@/components/public/PublicSearchForm.vue';
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

const currentSearch = computed(() => {
    const queryString = page.url.split('?')[1] ?? '';

    return new URLSearchParams(queryString).get('q') ?? '';
});

const themeStyle = computed(() => ({
    '--primary': String(site.appearance.primary_color || '#2563EB'),
    '--background': String(site.appearance.background_color || '#FAFAF7'),
    '--content-max-width': `${Number(site.appearance.content_max_width || 1280)}px`,
}));

const navigation = [
    { label: 'Katalog', href: '/library', prefixes: ['/library', '/search', '/book/'] },
    { label: 'Kategori', href: '/categories', prefixes: ['/categories', '/category/'] },
    { label: 'Penulis', href: '/authors', prefixes: ['/authors', '/author/'] },
    { label: 'Penerbit', href: '/publishers', prefixes: ['/publishers', '/publisher/'] },
    { label: 'Koleksi', href: '/collections', prefixes: ['/collections', '/collection/'] },
    { label: 'Tentang', href: '/about', prefixes: ['/about'] },
];

function isActive(prefixes: string[]) {
    return prefixes.some((prefix) => page.url === prefix || page.url.startsWith(prefix));
}

function closeMobile() {
    mobileOpen.value = false;
}

function toggleSearch() {
    searchOpen.value = !searchOpen.value;

    if (searchOpen.value) {
        mobileOpen.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-background text-foreground" :style="themeStyle">
        <Head>
            <link v-if="faviconUrl" head-key="favicon" rel="icon" :href="faviconUrl">
        </Head>

        <header class="sticky top-0 z-40 border-b border-border/70 bg-surface/95 backdrop-blur">
            <div
                class="mx-auto flex min-h-20 items-center justify-between gap-4 px-5 sm:px-8"
                style="max-width: var(--content-max-width)"
            >
                <Link href="/" class="flex min-w-0 items-center gap-3 text-lg font-semibold tracking-tight" @click="closeMobile">
                    <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-primary text-primary-foreground">
                        <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                        <BookOpen v-else class="size-4" />
                    </span>
                    <span class="truncate">{{ siteName }}</span>
                </Link>

                <nav class="hidden items-center gap-6 text-sm lg:flex">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="transition-colors"
                        :class="isActive(item.prefixes) ? 'font-medium text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-xl border border-border bg-surface text-muted-foreground hover:bg-muted hover:text-foreground"
                        :aria-expanded="searchOpen"
                        aria-label="Cari ebook"
                        @click="toggleSearch"
                    >
                        <X v-if="searchOpen" class="size-4" />
                        <Search v-else class="size-4" />
                    </button>

                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-xl border border-border bg-surface lg:hidden"
                        :aria-expanded="mobileOpen"
                        aria-label="Buka navigasi"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <X v-if="mobileOpen" class="size-5" />
                        <Menu v-else class="size-5" />
                    </button>
                </div>
            </div>

            <div v-if="searchOpen" class="border-t border-border bg-surface">
                <div
                    class="mx-auto px-5 py-4 sm:px-8"
                    style="max-width: var(--content-max-width)"
                >
                    <PublicSearchForm
                        :initial-query="currentSearch"
                        placeholder="Cari judul, penulis, kategori, tag, penerbit, koleksi, atau ISBN..."
                        compact
                    />
                </div>
            </div>

            <div v-if="mobileOpen" class="border-t border-border bg-surface lg:hidden">
                <nav
                    class="mx-auto grid gap-1 px-5 py-4 sm:px-8"
                    style="max-width: var(--content-max-width)"
                >
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-xl px-3 py-3 text-sm"
                        :class="isActive(item.prefixes) ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground'"
                        @click="closeMobile"
                    >
                        {{ item.label }}
                    </Link>
                    <Link
                        href="/contact"
                        class="rounded-xl px-3 py-3 text-sm"
                        :class="isActive(['/contact']) ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground'"
                        @click="closeMobile"
                    >
                        Kontak
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <footer class="mt-16 border-t border-border bg-surface">
            <div
                class="mx-auto grid gap-10 px-5 py-10 sm:px-8 md:grid-cols-[1.4fr_1fr_1fr]"
                style="max-width: var(--content-max-width)"
            >
                <div>
                    <div class="flex items-center gap-3 font-semibold">
                        <span class="flex size-9 items-center justify-center overflow-hidden rounded-xl bg-primary text-primary-foreground">
                            <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                            <BookOpen v-else class="size-4" />
                        </span>
                        <span>{{ siteName }}</span>
                    </div>
                    <p v-if="tagline" class="mt-4 max-w-md text-sm leading-6 text-muted-foreground">
                        {{ tagline }}
                    </p>
                </div>

                <div>
                    <p class="text-sm font-semibold">Jelajahi</p>
                    <div class="mt-4 grid gap-2 text-sm text-muted-foreground">
                        <Link href="/library" class="hover:text-foreground">Katalog ebook</Link>
                        <Link href="/categories" class="hover:text-foreground">Kategori</Link>
                        <Link href="/authors" class="hover:text-foreground">Penulis</Link>
                        <Link href="/publishers" class="hover:text-foreground">Penerbit</Link>
                        <Link href="/collections" class="hover:text-foreground">Koleksi</Link>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-semibold">Informasi</p>
                    <div class="mt-4 grid gap-2 text-sm text-muted-foreground">
                        <Link href="/about" class="hover:text-foreground">Tentang</Link>
                        <Link href="/contact" class="hover:text-foreground">Kontak</Link>
                        <span v-if="address" class="mt-2 flex items-start gap-2">
                            <MapPin class="mt-0.5 size-4 shrink-0" />
                            <span>{{ address }}</span>
                        </span>
                        <a v-if="phone" :href="`tel:${phone}`" class="flex items-center gap-2 hover:text-foreground">
                            <Phone class="size-4" />
                            {{ phone }}
                        </a>
                        <a v-if="email" :href="`mailto:${email}`" class="flex items-center gap-2 hover:text-foreground">
                            <Mail class="size-4" />
                            {{ email }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-t border-border">
                <div
                    class="mx-auto flex flex-col gap-2 px-5 py-5 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-8"
                    style="max-width: var(--content-max-width)"
                >
                    <span>© {{ new Date().getFullYear() }} {{ siteName }}</span>
                    <span>Perpustakaan digital publik</span>
                </div>
            </div>
        </footer>
    </div>
</template>
