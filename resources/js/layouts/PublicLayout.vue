<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Eye,
    Mail,
    MapPin,
    Menu,
    Phone,
    Search,
    Tags,
    Users,
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

function formatStatistic(value: number) {
    return new Intl.NumberFormat('id-ID', {
        notation: value >= 10000 ? 'compact' : 'standard',
        maximumFractionDigits: 1,
    }).format(Math.max(0, value));
}

function statisticIcon(key: string) {
    if (key === 'reads') return Eye;
    if (key === 'categories') return Tags;
    if (key === 'authors') return Users;

    return BookOpen;
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
            <div class="grid gap-6">
                <PublicSearchForm
                    :initial-query="currentSearch"
                    placeholder="Cari buku…"
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

        <footer class="safe-bottom mt-16 overflow-hidden bg-[#151B28] text-white sm:mt-20">
            <div class="border-b border-white/10">
                <div
                    class="mx-auto flex flex-col gap-5 px-[var(--page-gutter)] py-7 sm:flex-row sm:items-center sm:justify-between lg:py-8"
                    style="max-width: var(--content-max-width)"
                >
                    <div class="flex max-w-2xl items-start gap-3.5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-[var(--radius-lg)] border border-white/10 bg-white/[0.06] text-white">
                            <BookOpen class="size-5" />
                        </span>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/50">Ruang baca digital</p>
                            <h2 class="mt-1 text-xl font-semibold tracking-[-0.02em] text-white sm:text-2xl">
                                Temukan bacaan yang layak dibawa lebih jauh.
                            </h2>
                            <p class="mt-1.5 text-sm leading-6 text-white/60">
                                Akses koleksi publik, baca langsung di browser, dan lanjutkan rasa ingin tahu Anda.
                            </p>
                        </div>
                    </div>

                    <Link
                        href="/library"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-[var(--radius-md)] bg-white px-4 text-sm font-semibold text-[#151B28] transition-colors hover:bg-white/90"
                    >
                        Mulai membaca
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>

            <div
                class="mx-auto grid gap-10 px-[var(--page-gutter)] py-10 sm:grid-cols-2 lg:grid-cols-[1.45fr_.72fr_1fr_1.05fr] lg:gap-12 lg:py-12"
                style="max-width: var(--content-max-width)"
            >
                <div class="sm:col-span-2 lg:col-span-1">
                    <Link href="/" class="inline-flex items-center gap-3 font-semibold tracking-tight">
                        <span class="grid size-11 place-items-center overflow-hidden rounded-[var(--radius-lg)] border border-white/10 bg-white/[0.07] text-white">
                            <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                            <BookOpen v-else class="size-5" />
                        </span>
                        <span class="text-lg">{{ siteName }}</span>
                    </Link>

                    <p class="mt-4 max-w-md text-sm leading-6 text-white/60">
                        {{ description }}
                    </p>

                    <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.05] px-3 py-1.5 text-[11px] font-semibold text-white/70">
                        <span class="size-1.5 rounded-full bg-emerald-400" />
                        Akses publik · Baca tanpa login
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Jelajahi</p>
                    <nav class="mt-4 grid gap-2.5 text-sm" aria-label="Navigasi footer">
                        <Link href="/" class="text-white/70 transition-colors hover:text-white">Beranda</Link>
                        <Link href="/library" class="text-white/70 transition-colors hover:text-white">Katalog Buku</Link>
                        <Link href="/categories" class="text-white/70 transition-colors hover:text-white">Kategori</Link>
                        <Link href="/collections" class="text-white/70 transition-colors hover:text-white">Koleksi</Link>
                        <Link href="/authors" class="text-white/70 transition-colors hover:text-white">Penulis</Link>
                        <Link href="/about" class="text-white/70 transition-colors hover:text-white">Tentang</Link>
                    </nav>
                </div>

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Kontak</p>

                    <div v-if="address || phone || email" class="mt-4 grid gap-3 text-sm leading-5 text-white/60">
                        <span v-if="address" class="flex items-start gap-2.5">
                            <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-md)] border border-white/10 bg-white/[0.05]">
                                <MapPin class="size-3.5" />
                            </span>
                            <span class="pt-1.5">{{ address }}</span>
                        </span>

                        <a v-if="phone" :href="'tel:' + phone" class="flex items-start gap-2.5 transition-colors hover:text-white">
                            <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-md)] border border-white/10 bg-white/[0.05]">
                                <Phone class="size-3.5" />
                            </span>
                            <span class="pt-1.5">{{ phone }}</span>
                        </a>

                        <a v-if="email" :href="'mailto:' + email" class="flex items-start gap-2.5 break-all transition-colors hover:text-white">
                            <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-md)] border border-white/10 bg-white/[0.05]">
                                <Mail class="size-3.5" />
                            </span>
                            <span class="pt-1.5">{{ email }}</span>
                        </a>
                    </div>

                    <Link
                        v-else
                        href="/contact"
                        class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-white/70 transition-colors hover:text-white"
                    >
                        Lihat informasi kontak
                        <ArrowRight class="size-3.5" />
                    </Link>
                </div>

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Perpustakaan dalam angka</p>

                    <div v-if="footerStats.length" class="mt-4 grid grid-cols-2 gap-2.5">
                        <div
                            v-for="stat in footerStats"
                            :key="stat.key"
                            class="rounded-[var(--radius-lg)] border border-white/10 bg-white/[0.045] p-3"
                        >
                            <component
                                :is="statisticIcon(stat.key)"
                                class="size-3.5 text-white/50"
                                aria-hidden="true"
                            />
                            <p class="mt-3 text-xl font-semibold tabular-nums tracking-[-0.02em] text-white">
                                {{ formatStatistic(stat.value) }}
                            </p>
                            <p class="mt-0.5 text-[10px] font-medium leading-4 text-white/50">{{ stat.label }}</p>
                        </div>
                    </div>

                    <div v-else class="mt-4 rounded-[var(--radius-lg)] border border-white/10 bg-white/[0.045] p-4">
                        <p class="text-sm font-semibold text-white">Koleksi terus bertumbuh</p>
                        <p class="mt-1 text-xs leading-5 text-white/60">Temukan buku baru melalui katalog dan kategori yang tersedia.</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10">
                <div
                    class="mx-auto flex flex-col gap-2 px-[var(--page-gutter)] py-5 text-[11px] text-white/40 sm:flex-row sm:items-center sm:justify-between"
                    style="max-width: var(--content-max-width)"
                >
                    <span>© {{ new Date().getFullYear() }} {{ siteName }}. Hak cipta dilindungi.</span>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                        <Link href="/publishers" class="transition-colors hover:text-white/75">Penerbit</Link>
                        <Link href="/contact" class="transition-colors hover:text-white/75">Kontak</Link>
                        <span>{{ tagline || 'Baca lebih nyaman, temukan lebih mudah.' }}</span>
                    </div>
                </div>
            </div>
        </footer>

        <PwaStatus />
    </div>
</template>
