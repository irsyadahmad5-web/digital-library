<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    LayoutDashboard,
    LibraryBig,
    LogOut,
    Menu,
    PanelsTopLeft,
    ScrollText,
    Settings,
    UserRound,
} from '@lucide/vue';
import { Popover } from '@/components/ui/popover';
import { SheetShell } from '@/components/ui/sheet';
import { Tooltip } from '@/components/ui/tooltip';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const user = computed(() => page.props.auth.user);
const siteName = computed(() => String(page.props.site.general.site_name || 'Digital Library'));
const logoUrl = computed(() => page.props.site.general.logo_url ? String(page.props.site.general.logo_url) : null);
const currentPath = computed(() => page.url.split('?')[0] || '/admin');
const sidebarCollapsed = ref(false);
const mobileOpen = ref(false);

const SIDEBAR_STORAGE_KEY = 'digital-library.admin.sidebar.collapsed.v1';

const menuGroups = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', href: '/admin', icon: LayoutDashboard, permission: 'admin.access' },
            { label: 'Analytics', href: '/admin/analytics', icon: BarChart3, permission: 'admin.view-analytics', prefix: '/admin/analytics' },
        ],
    },
    {
        label: 'Library',
        items: [
            { label: 'Ebook', href: '/admin/ebooks', icon: BookOpen, permission: 'library.manage-ebooks', prefix: '/admin/ebooks' },
            { label: 'Master Data', href: '/admin/master-data/categories', icon: LibraryBig, permission: 'library.manage-master-data', prefix: '/admin/master-data' },
        ],
    },
    {
        label: 'Presentation',
        items: [
            { label: 'Homepage Builder', href: '/admin/homepage-builder', icon: PanelsTopLeft, permission: 'admin.manage-settings', prefix: '/admin/homepage-builder' },
            { label: 'Pengaturan', href: '/admin/settings/general', icon: Settings, permission: 'admin.manage-settings', prefix: '/admin/settings' },
        ],
    },
    {
        label: 'System',
        items: [
            { label: 'Audit Log', href: '/admin/audit-log', icon: ScrollText, permission: 'admin.view-audit', prefix: '/admin/audit-log' },
            { label: 'Profil & Keamanan', href: '/admin/profile', icon: UserRound, permission: 'admin.access', prefix: '/admin/profile' },
        ],
    },
];

type MenuItem = (typeof menuGroups)[number]['items'][number];

function can(permission: string) {
    return user.value?.permissions.includes(permission) ?? false;
}

const visibleGroups = computed(() => menuGroups
    .map((group) => ({
        ...group,
        items: group.items.filter((entry) => can(entry.permission)),
    }))
    .filter((group) => group.items.length > 0));

function isActive(item: MenuItem) {
    if (item.prefix) {
        return currentPath.value === item.href || currentPath.value.startsWith(item.prefix);
    }

    return currentPath.value === item.href;
}

const activeItem = computed(() => visibleGroups.value
    .flatMap((group) => group.items)
    .find((item) => isActive(item)));

const currentSection = computed(() => activeItem.value?.label || 'Administration');
const primaryRole = computed(() => user.value?.roles[0] || 'Admin');
const userInitials = computed(() => {
    const value = user.value?.name?.trim();

    if (!value) return 'A';

    return value
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

function persistSidebar() {
    try {
        window.localStorage.setItem(
            SIDEBAR_STORAGE_KEY,
            sidebarCollapsed.value ? '1' : '0',
        );
    } catch {
        // Layout remains functional when local storage is unavailable.
    }
}

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    persistSidebar();
}

function logout() {
    router.post('/admin/logout');
}

const removeNavigateListener = router.on('navigate', () => {
    mobileOpen.value = false;
});

onMounted(() => {
    try {
        sidebarCollapsed.value = window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    } catch {
        sidebarCollapsed.value = false;
    }
});

onBeforeUnmount(() => {
    removeNavigateListener();
});
</script>

<template>
    <div
        class="min-h-screen bg-canvas text-ink transition-[grid-template-columns] duration-200 md:grid"
        :class="sidebarCollapsed
            ? 'md:grid-cols-[72px_minmax(0,1fr)]'
            : 'md:grid-cols-[248px_minmax(0,1fr)]'"
    >
        <aside class="sticky top-0 hidden h-dvh border-r border-line bg-surface md:flex md:flex-col">
            <div
                class="flex min-h-16 items-center border-b border-line"
                :class="sidebarCollapsed ? 'justify-center px-2' : 'gap-2.5 px-4'"
            >
                <Link
                    href="/admin"
                    class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-[var(--radius-md)] bg-brand text-brand-foreground shadow-sm"
                    aria-label="Dashboard admin"
                >
                    <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover">
                    <BookOpen v-else class="size-[17px]" />
                </Link>

                <div v-if="!sidebarCollapsed" class="min-w-0">
                    <p class="truncate text-sm font-semibold tracking-tight">{{ siteName }}</p>
                    <p class="mt-0.5 text-[10px] font-medium uppercase tracking-[0.1em] text-ink-faint">Administration</p>
                </div>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-2 py-3" aria-label="Navigasi admin">
                <section
                    v-for="(group, groupIndex) in visibleGroups"
                    :key="group.label"
                    :class="groupIndex ? 'mt-4' : ''"
                >
                    <p
                        v-if="!sidebarCollapsed"
                        class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-ink-faint"
                    >
                        {{ group.label }}
                    </p>
                    <div v-else-if="groupIndex" class="mx-2 mb-2 h-px bg-line" />

                    <div class="grid gap-0.5">
                        <template v-for="item in group.items" :key="item.href">
                            <Tooltip v-if="sidebarCollapsed" :text="item.label" side="right">
                                <Link
                                    :href="item.href"
                                    class="grid min-h-11 place-items-center rounded-[var(--radius-md)] transition-colors"
                                    :class="isActive(item)
                                        ? 'bg-brand-soft text-brand'
                                        : 'text-ink-soft hover:bg-surface-subtle hover:text-ink'"
                                    :aria-current="isActive(item) ? 'page' : undefined"
                                >
                                    <component :is="item.icon" class="size-[18px]" />
                                    <span class="sr-only">{{ item.label }}</span>
                                </Link>
                            </Tooltip>

                            <Link
                                v-else
                                :href="item.href"
                                class="flex min-h-10 items-center gap-2.5 rounded-[var(--radius-md)] px-2.5 text-[13px] font-medium transition-colors"
                                :class="isActive(item)
                                    ? 'bg-brand-soft text-brand'
                                    : 'text-ink-soft hover:bg-surface-subtle hover:text-ink'"
                                :aria-current="isActive(item) ? 'page' : undefined"
                            >
                                <component :is="item.icon" class="size-4 shrink-0" />
                                <span class="truncate">{{ item.label }}</span>
                            </Link>
                        </template>
                    </div>
                </section>
            </nav>

            <div class="border-t border-line p-2">
                <Tooltip v-if="sidebarCollapsed" :text="user?.name || 'Profil admin'" side="right">
                    <Link
                        href="/admin/profile"
                        class="mx-auto grid size-11 place-items-center rounded-[var(--radius-md)] bg-surface-subtle text-xs font-semibold text-ink transition-colors hover:bg-brand-soft hover:text-brand"
                        aria-label="Profil dan keamanan"
                    >
                        {{ userInitials }}
                    </Link>
                </Tooltip>

                <Link
                    v-else
                    href="/admin/profile"
                    class="flex min-w-0 items-center gap-2.5 rounded-[var(--radius-md)] px-2.5 py-2 transition-colors hover:bg-surface-subtle"
                >
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-md)] bg-brand-soft text-[11px] font-semibold text-brand">
                        {{ userInitials }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-semibold text-ink">{{ user?.name }}</span>
                        <span class="mt-0.5 block truncate text-[10px] text-ink-faint">{{ primaryRole }}</span>
                    </span>
                </Link>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-30 border-b border-line bg-surface/96 backdrop-blur-md">
                <div class="flex min-h-14 items-center gap-2 px-3 sm:px-4 lg:px-5">
                    <button
                        type="button"
                        class="grid size-11 shrink-0 place-items-center rounded-[var(--radius-md)] text-ink-soft hover:bg-surface-subtle hover:text-ink md:hidden"
                        aria-label="Buka navigasi admin"
                        :aria-expanded="mobileOpen"
                        @click="mobileOpen = true"
                    >
                        <Menu class="size-5" />
                    </button>

                    <button
                        type="button"
                        class="hidden size-9 shrink-0 place-items-center rounded-[var(--radius-md)] text-ink-soft transition-colors hover:bg-surface-subtle hover:text-ink md:grid"
                        :aria-label="sidebarCollapsed ? 'Perluas sidebar' : 'Ringkas sidebar'"
                        :title="sidebarCollapsed ? 'Perluas sidebar' : 'Ringkas sidebar'"
                        @click="toggleSidebar"
                    >
                        <ChevronRight v-if="sidebarCollapsed" class="size-4" />
                        <ChevronLeft v-else class="size-4" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-ink">{{ currentSection }}</p>
                        <p class="hidden truncate text-[10px] text-ink-faint sm:block">{{ siteName }}</p>
                    </div>

                    <a
                        href="/"
                        target="_blank"
                        rel="noopener"
                        class="hidden min-h-9 items-center gap-1.5 rounded-[var(--radius-md)] px-2.5 text-xs font-semibold text-ink-soft transition-colors hover:bg-surface-subtle hover:text-ink sm:inline-flex"
                    >
                        Lihat situs
                        <ExternalLink class="size-3.5" />
                    </a>

                    <Popover align="end">
                        <template #trigger>
                            <button
                                type="button"
                                class="flex min-h-10 max-w-48 items-center gap-2 rounded-[var(--radius-md)] px-1.5 pr-2 transition-colors hover:bg-surface-subtle"
                                aria-label="Buka menu akun"
                            >
                                <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-md)] bg-brand-soft text-[11px] font-semibold text-brand">
                                    {{ userInitials }}
                                </span>
                                <span class="hidden min-w-0 text-left lg:block">
                                    <span class="block truncate text-xs font-semibold text-ink">{{ user?.name }}</span>
                                    <span class="mt-0.5 block truncate text-[10px] text-ink-faint">{{ primaryRole }}</span>
                                </span>
                            </button>
                        </template>

                        <div class="w-64 p-1">
                            <div class="border-b border-line px-2.5 pb-3 pt-2">
                                <p class="truncate text-sm font-semibold text-ink">{{ user?.name }}</p>
                                <p class="mt-1 truncate text-xs text-ink-soft">{{ user?.email }}</p>
                            </div>

                            <div class="grid gap-0.5 py-1.5">
                                <Link
                                    href="/admin/profile"
                                    class="flex min-h-9 items-center gap-2 rounded-[var(--radius-md)] px-2.5 text-xs font-medium text-ink-soft hover:bg-surface-subtle hover:text-ink"
                                >
                                    <UserRound class="size-4" />
                                    Profil & Keamanan
                                </Link>
                                <a
                                    href="/"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex min-h-9 items-center gap-2 rounded-[var(--radius-md)] px-2.5 text-xs font-medium text-ink-soft hover:bg-surface-subtle hover:text-ink sm:hidden"
                                >
                                    <ExternalLink class="size-4" />
                                    Lihat situs
                                </a>
                            </div>

                            <div class="border-t border-line pt-1.5">
                                <button
                                    type="button"
                                    class="flex min-h-9 w-full items-center gap-2 rounded-[var(--radius-md)] px-2.5 text-left text-xs font-semibold text-danger hover:bg-danger-soft"
                                    @click="logout"
                                >
                                    <LogOut class="size-4" />
                                    Keluar
                                </button>
                            </div>
                        </div>
                    </Popover>
                </div>
            </header>

            <main class="min-w-0 px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-7">
                <div class="mx-auto w-full max-w-[1600px]">
                    <slot />
                </div>
            </main>
        </div>

        <SheetShell
            :open="mobileOpen"
            side="left"
            title="Administration"
            :description="siteName"
            @update:open="mobileOpen = $event"
        >
            <div class="grid gap-5">
                <nav class="grid gap-4" aria-label="Navigasi admin mobile">
                    <section v-for="group in visibleGroups" :key="group.label">
                        <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-ink-faint">
                            {{ group.label }}
                        </p>
                        <div class="mt-1 grid gap-0.5">
                            <Link
                                v-for="item in group.items"
                                :key="item.href"
                                :href="item.href"
                                class="flex min-h-11 items-center gap-3 rounded-[var(--radius-md)] px-2.5 text-sm font-medium transition-colors"
                                :class="isActive(item)
                                    ? 'bg-brand-soft text-brand'
                                    : 'text-ink-soft hover:bg-surface-subtle hover:text-ink'"
                                :aria-current="isActive(item) ? 'page' : undefined"
                            >
                                <component :is="item.icon" class="size-[18px] shrink-0" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </div>
                    </section>
                </nav>

                <div class="border-t border-line pt-4">
                    <div class="flex min-w-0 items-center gap-3 px-2">
                        <span class="grid size-10 shrink-0 place-items-center rounded-[var(--radius-md)] bg-brand-soft text-xs font-semibold text-brand">
                            {{ userInitials }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">{{ user?.name }}</p>
                            <p class="mt-0.5 truncate text-xs text-ink-soft">{{ user?.email }}</p>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-1">
                        <a
                            href="/"
                            target="_blank"
                            rel="noopener"
                            class="flex min-h-11 items-center gap-3 rounded-[var(--radius-md)] px-2.5 text-sm font-medium text-ink-soft hover:bg-surface-subtle hover:text-ink"
                        >
                            <ExternalLink class="size-[18px]" />
                            Lihat situs publik
                        </a>
                        <button
                            type="button"
                            class="flex min-h-11 items-center gap-3 rounded-[var(--radius-md)] px-2.5 text-left text-sm font-semibold text-danger hover:bg-danger-soft"
                            @click="logout"
                        >
                            <LogOut class="size-[18px]" />
                            Keluar
                        </button>
                    </div>
                </div>
            </div>
        </SheetShell>
    </div>
</template>
