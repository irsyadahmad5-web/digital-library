<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
import PwaStatus from '@/components/public/PwaStatus.vue';
import { usePublicOnlineVisitors } from '@/composables/publicOnlineVisitors';
import { IconButton } from '@/components/ui/icon-button';
import { SheetShell } from '@/components/ui/sheet';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const site = page.props.site;
const mobileOpen = ref(false);
const searchOpen = ref(false);
let rootScrollbarTimer: number | null = null;

const siteName = computed(() => String(site.general.site_name || 'Digital Library'));
const logoUrl = computed(() => site.general.logo_url ? String(site.general.logo_url) : null);
const faviconUrl = computed(() => site.general.favicon_url ? String(site.general.favicon_url) : null);
const tagline = computed(() => String(site.general.tagline || ''));
const description = computed(() => String(
    site.general.description
    || 'Perpustakaan digital untuk membaca, belajar, dan menemukan pengetahuan baru.',
));
const address = computed(() => String(site.general.address || ''));
const phone = computed(() => String(site.general.phone || ''));
const email = computed(() => String(site.general.email || ''));
const footerStats = computed(() => Array.isArray(page.props.publicStats) ? page.props.publicStats : []);
const { onlineVisitors } = usePublicOnlineVisitors();
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
    { label: 'Beranda', href: '/', prefixes: ['/'] },
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
    return prefixes.some((prefix) => prefix === '/'
        ? currentPath.value === '/'
        : currentPath.value === prefix || currentPath.value.startsWith(prefix));
}

function formatStatistic(value: number) {
    return new Intl.NumberFormat('id-ID', {
        notation: value >= 10000 ? 'compact' : 'standard',
        maximumFractionDigits: 1,
    }).format(Math.max(0, value));
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

function hideRootScrollbarSoon() {
    if (rootScrollbarTimer !== null) {
        window.clearTimeout(rootScrollbarTimer);
    }

    rootScrollbarTimer = window.setTimeout(() => {
        document.documentElement.classList.remove('public-scrollbar-active');
        rootScrollbarTimer = null;
    }, 850);
}

function activateRootScrollbar() {
    document.documentElement.classList.add('public-scrollbar-active');
    hideRootScrollbarSoon();
}

function revealRootScrollbarNearEdge(event: PointerEvent) {
    if (window.innerWidth - event.clientX <= 36) {
        activateRootScrollbar();
    }
}

onMounted(() => {
    document.documentElement.classList.add('public-scrollbar-scope');
    window.addEventListener('scroll', activateRootScrollbar, { passive: true });
    window.addEventListener('wheel', activateRootScrollbar, { passive: true });
    window.addEventListener('touchmove', activateRootScrollbar, { passive: true });
    window.addEventListener('pointermove', revealRootScrollbarNearEdge, { passive: true });
});

onBeforeUnmount(() => {
    removeNavigateListener();
    window.removeEventListener('scroll', activateRootScrollbar);
    window.removeEventListener('wheel', activateRootScrollbar);
    window.removeEventListener('touchmove', activateRootScrollbar);
    window.removeEventListener('pointermove', revealRootScrollbarNearEdge);

    if (rootScrollbarTimer !== null) {
        window.clearTimeout(rootScrollbarTimer);
        rootScrollbarTimer = null;
    }

    document.documentElement.classList.remove(
        'public-scrollbar-scope',
        'public-scrollbar-active',
    );
});
</script>

<template>
    <div class="public-shell safe-x min-h-screen bg-canvas text-ink" :style="themeStyle">
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

                <div class="ml-auto hidden w-[min(27vw,21rem)] lg:block">
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
                        :label="searchOpen ? 'Tutup pencarian' : 'Cari buku'"
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
            <div class="grid min-w-0 w-full gap-4 overflow-x-hidden">
                <PublicSearchForm
                    :initial-query="currentSearch"
                    placeholder="Cari buku…"
                    compact
                />

                <nav class="grid min-w-0 gap-1" aria-label="Navigasi mobile">
                    <Link
                        v-for="item in primaryNavigation"
                        :key="item.href"
                        :href="item.href"
                        class="flex min-h-10 min-w-0 items-center rounded-[var(--radius-md)] px-3 text-sm font-medium"
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
                            class="flex min-h-10 min-w-0 items-center rounded-[var(--radius-md)] px-3 text-sm text-ink-soft hover:bg-surface-subtle hover:text-ink"
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

        <footer class="safe-bottom mt-8 overflow-hidden bg-[#151B28] text-white sm:mt-14">
            <div
                class="mx-auto grid gap-7 px-[var(--page-gutter)] py-8 sm:grid-cols-2 sm:gap-9 sm:py-10 lg:grid-cols-[1.15fr_.65fr_1fr_1fr]"
                style="max-width: var(--content-max-width)"
            >
                <div class="min-w-0">
                    <Link href="/" class="inline-flex min-w-0 items-center gap-2.5 font-semibold tracking-tight">
                        <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg border border-white/10 bg-white/[0.07]">
                            <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                            <BookOpen v-else class="size-5" />
                        </span>
                        <span class="truncate text-base">{{ siteName }}</span>
                    </Link>
                    <p class="mt-3 max-w-sm text-xs leading-5 text-white/60 sm:text-sm sm:leading-6">{{ description }}</p>
                    <p class="mt-3 inline-flex items-center gap-2 text-[11px] text-white/65">
                        <span class="size-1.5 rounded-full bg-emerald-400" />
                        Baca buku tanpa login
                    </p>
                </div>

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-white/50">Jelajahi</p>
                    <nav class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs sm:grid-cols-1 sm:text-sm" aria-label="Navigasi footer">
                        <Link href="/" class="text-white/70 hover:text-white">Beranda</Link>
                        <Link href="/library" class="text-white/70 hover:text-white">Katalog Buku</Link>
                        <Link href="/categories" class="text-white/70 hover:text-white">Kategori</Link>
                        <Link href="/collections" class="text-white/70 hover:text-white">Koleksi</Link>
                        <Link href="/authors" class="text-white/70 hover:text-white">Penulis</Link>
                        <Link href="/about" class="text-white/70 hover:text-white">Tentang</Link>
                    </nav>
                </div>

                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-white/50">Kontak</p>
                    <div class="mt-3 grid min-w-0 gap-3 text-xs leading-5 sm:text-sm">
                        <div class="flex min-w-0 items-start gap-2.5 text-white/70">
                            <MapPin class="mt-0.5 size-4 shrink-0 text-white/50" aria-hidden="true" />
                            <span class="min-w-0 break-words">{{ address || 'Alamat belum dicantumkan' }}</span>
                        </div>
                        <div class="flex min-w-0 items-start gap-2.5 text-white/70">
                            <Phone class="mt-0.5 size-4 shrink-0 text-white/50" aria-hidden="true" />
                            <a v-if="phone" :href="'tel:' + phone" class="min-w-0 break-words hover:text-white">{{ phone }}</a>
                            <span v-else>Telepon belum dicantumkan</span>
                        </div>
                        <div class="flex min-w-0 items-start gap-2.5 text-white/70">
                            <Mail class="mt-0.5 size-4 shrink-0 text-white/50" aria-hidden="true" />
                            <a v-if="email" :href="'mailto:' + email" class="min-w-0 break-all hover:text-white">{{ email }}</a>
                            <span v-else>Email belum dicantumkan</span>
                        </div>
                    </div>
                </div>

                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-white/50">Statistik</p>
                    <div v-if="footerStats.length" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3">
                        <div v-for="stat in footerStats" :key="stat.key" class="min-w-0">
                            <p class="text-lg font-semibold leading-tight tabular-nums text-white sm:text-xl">{{ formatStatistic(stat.value) }}</p>
                            <p class="mt-0.5 text-[11px] leading-4 text-white/50">{{ stat.label }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 border-t border-white/10 pt-3 text-xs" role="status" aria-live="off">
                        <span class="size-2 shrink-0 rounded-full" :class="onlineVisitors !== null ? 'bg-emerald-400' : 'bg-white/30'" />
                        <span class="text-white/60">Pengguna online</span>
                        <span class="ml-auto font-semibold tabular-nums text-white">{{ onlineVisitors === null ? '—' : formatStatistic(onlineVisitors) }}</span>
                    </div>
                    <p class="mt-1 text-[10px] leading-4 text-white/35">Aktif dalam 2 menit terakhir</p>
                </div>
            </div>
            <div class="border-t border-white/10">
                <div
                    class="mx-auto flex flex-wrap items-center justify-between gap-2 px-[var(--page-gutter)] py-4 text-[11px] text-white/45"
                    style="max-width: var(--content-max-width)"
                >
                    <span>© {{ new Date().getFullYear() }} {{ siteName }}</span>
                    <span>{{ tagline || 'Baca lebih nyaman, temukan lebih mudah.' }}</span>
                </div>
            </div>
        </footer>

        <PwaStatus />
    </div>
</template>
