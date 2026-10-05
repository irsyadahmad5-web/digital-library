<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { BarChart3, BookOpen, LayoutDashboard, LibraryBig, PanelsTopLeft, ScrollText, Settings, UserRound } from '@lucide/vue';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const user = page.props.auth.user;
const siteName = page.props.site.general.site_name || 'Digital Library';
const logoUrl = page.props.site.general.logo_url;

const menu = [
    { label: 'Dashboard', href: '/admin', icon: LayoutDashboard, permission: 'admin.access' },
    { label: 'Analytics', href: '/admin/analytics', icon: BarChart3, permission: 'admin.view-analytics', prefix: '/admin/analytics' },
    { label: 'Ebook', href: '/admin/ebooks', icon: BookOpen, permission: 'library.manage-ebooks', prefix: '/admin/ebooks' },
    { label: 'Master Data', href: '/admin/master-data/categories', icon: LibraryBig, permission: 'library.manage-master-data', prefix: '/admin/master-data' },
    { label: 'Homepage Builder', href: '/admin/homepage-builder', icon: PanelsTopLeft, permission: 'admin.manage-settings', prefix: '/admin/homepage-builder' },
    { label: 'Pengaturan', href: '/admin/settings/general', icon: Settings, permission: 'admin.manage-settings', prefix: '/admin/settings' },
    { label: 'Profil & Keamanan', href: '/admin/profile', icon: UserRound, permission: 'admin.access' },
    { label: 'Audit Log', href: '/admin/audit-log', icon: ScrollText, permission: 'admin.view-audit' },
];

function can(permission: string) {
    return user?.permissions.includes(permission) ?? false;
}

function isActive(item: (typeof menu)[number]) {
    return item.prefix ? page.url.startsWith(item.prefix) : page.url === item.href;
}

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="min-h-screen bg-background md:grid md:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="hidden border-r border-border bg-surface md:flex md:min-h-screen md:flex-col">
            <div class="flex min-h-20 items-center gap-3 border-b border-border px-6">
                <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-primary text-primary-foreground">
                    <img v-if="logoUrl" :src="String(logoUrl)" alt="" class="size-full object-cover">
                    <BookOpen v-else class="size-5" />
                </div>
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ siteName }}</p>
                    <p class="text-xs text-muted-foreground">Administration</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 p-4">
                <Link
                    v-for="item in menu.filter((entry) => can(entry.permission))"
                    :key="item.href"
                    :href="item.href"
                    class="flex min-h-11 items-center gap-3 rounded-xl px-4 text-sm text-foreground transition-colors hover:bg-muted"
                    :class="{ 'bg-primary/10 text-primary': isActive(item) }"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </Link>

                <div class="my-4 border-t border-border" />
            </nav>

            <div class="border-t border-border p-4">
                <div class="rounded-2xl bg-muted p-4">
                    <p class="truncate text-sm font-medium">{{ user?.name }}</p>
                    <p class="mt-1 truncate text-xs text-muted-foreground">{{ user?.email }}</p>
                    <button
                        class="mt-4 text-xs font-medium text-primary hover:underline"
                        type="button"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="flex min-h-16 items-center justify-between border-b border-border bg-surface px-5 md:hidden">
                <Link href="/admin" class="max-w-[70%] truncate font-semibold">{{ siteName }}</Link>
                <Link href="/admin/profile" class="text-sm text-primary">Profil</Link>
            </header>

            <main class="min-w-0 p-5 sm:p-8 lg:p-10">
                <slot />
            </main>
        </div>
    </div>
</template>
