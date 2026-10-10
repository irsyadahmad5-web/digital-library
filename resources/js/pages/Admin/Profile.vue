<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, MonitorSmartphone, ShieldCheck, UserRound } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/ui/page-header';
import type { SharedPageProps } from '@/types';

interface ProfileData {
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
    last_login_at: string | null;
    last_login_ip: string | null;
}

interface SessionItem {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
    is_current: boolean;
}

const props = defineProps<{
    profile: ProfileData;
    sessions: SessionItem[];
}>();

const page = usePage<SharedPageProps>();

const profileForm = useForm({
    name: props.profile.name,
    email: props.profile.email,
    current_password: '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const sessionsForm = useForm({
    current_password: '',
});

function updateProfile() {
    profileForm.patch('/admin/profile', {
        preserveScroll: true,
        onSuccess: () => profileForm.reset('current_password'),
    });
}

function updatePassword() {
    passwordForm.put('/admin/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function revokeOthers() {
    sessionsForm.delete('/admin/sessions/others', {
        preserveScroll: true,
        onSuccess: () => sessionsForm.reset(),
    });
}

function formatTime(timestamp: number) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(timestamp * 1000));
}
</script>

<template>
    <Head title="Profil & Keamanan" />

    <AdminLayout>
        <div class="grid gap-5">
            <PageHeader
                eyebrow="System"
                title="Profil & Keamanan"
                description="Kelola identitas admin, password, role, dan sesi perangkat dari satu tempat."
            />

            <Alert v-if="page.props.flash.status" tone="success" :title="page.props.flash.status" />

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
                    <div class="flex items-start gap-3 border-b border-line px-4 py-3.5">
                        <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] bg-brand-soft text-brand">
                            <UserRound class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-ink">Informasi profil</h2>
                            <p class="mt-1 text-xs text-ink-soft">Konfirmasi password saat mengubah data sensitif.</p>
                        </div>
                    </div>

                    <form class="grid gap-4 p-4" @submit.prevent="updateProfile">
                        <label class="grid gap-1.5">
                            <span class="text-xs font-semibold text-ink">Nama</span>
                            <input v-model="profileForm.name" class="ui-control ui-focus-ring w-full px-3 text-sm">
                            <p v-if="profileForm.errors.name" class="text-xs text-danger">{{ profileForm.errors.name }}</p>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-xs font-semibold text-ink">Email</span>
                            <input v-model="profileForm.email" type="email" class="ui-control ui-focus-ring w-full px-3 text-sm">
                            <p v-if="profileForm.errors.email" class="text-xs text-danger">{{ profileForm.errors.email }}</p>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-xs font-semibold text-ink">Password saat ini</span>
                            <input
                                v-model="profileForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                class="ui-control ui-focus-ring w-full px-3 text-sm"
                            >
                            <p v-if="profileForm.errors.current_password" class="text-xs text-danger">{{ profileForm.errors.current_password }}</p>
                        </label>

                        <div class="flex justify-end">
                            <Button :disabled="profileForm.processing">Simpan profil</Button>
                        </div>
                    </form>

                    <div class="border-t border-line px-4 py-3">
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="role in profile.roles" :key="role" tone="brand">{{ role }}</Badge>
                            <Badge v-if="!profile.roles.length" tone="neutral">Tanpa role</Badge>
                        </div>
                        <dl class="mt-3 grid gap-2 text-[11px] sm:grid-cols-2">
                            <div>
                                <dt class="text-ink-faint">Login terakhir</dt>
                                <dd class="mt-0.5 font-medium text-ink">{{ profile.last_login_at || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-ink-faint">IP terakhir</dt>
                                <dd class="mt-0.5 font-mono text-ink">{{ profile.last_login_ip || '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
                    <div class="flex items-start gap-3 border-b border-line px-4 py-3.5">
                        <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] bg-surface-subtle text-ink-soft">
                            <KeyRound class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-ink">Ganti password</h2>
                            <p class="mt-1 text-xs text-ink-soft">Minimal 10 karakter, huruf besar/kecil, angka, dan simbol.</p>
                        </div>
                    </div>

                    <form class="grid gap-4 p-4" @submit.prevent="updatePassword">
                        <label class="grid gap-1.5">
                            <span class="text-xs font-semibold text-ink">Password saat ini</span>
                            <input
                                v-model="passwordForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                class="ui-control ui-focus-ring w-full px-3 text-sm"
                            >
                            <p v-if="passwordForm.errors.current_password" class="text-xs text-danger">{{ passwordForm.errors.current_password }}</p>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-xs font-semibold text-ink">Password baru</span>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                class="ui-control ui-focus-ring w-full px-3 text-sm"
                            >
                            <p v-if="passwordForm.errors.password" class="text-xs text-danger">{{ passwordForm.errors.password }}</p>
                        </label>

                        <label class="grid gap-1.5">
                            <span class="text-xs font-semibold text-ink">Ulangi password baru</span>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="ui-control ui-focus-ring w-full px-3 text-sm"
                            >
                        </label>

                        <div class="flex justify-end">
                            <Button :disabled="passwordForm.processing">
                                <ShieldCheck class="size-4" />
                                Perbarui password
                            </Button>
                        </div>
                    </form>
                </section>
            </div>

            <section class="rounded-[var(--radius-lg)] border border-line bg-surface">
                <div class="flex items-start gap-3 border-b border-line px-4 py-3.5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] bg-surface-subtle text-ink-soft">
                        <MonitorSmartphone class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">Sesi aktif</h2>
                        <p class="mt-1 text-xs text-ink-soft">Keluarkan perangkat lain bila ada sesi yang tidak dikenali.</p>
                    </div>
                </div>

                <div class="divide-y divide-line">
                    <div
                        v-for="session in sessions"
                        :key="session.id"
                        class="grid gap-2 px-4 py-3 text-xs sm:grid-cols-[150px_minmax(0,1fr)_auto] sm:items-center"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-ink">{{ session.ip_address || 'IP tidak tersedia' }}</span>
                            <Badge v-if="session.is_current" tone="success">Sesi ini</Badge>
                        </div>
                        <span class="truncate text-ink-soft">{{ session.user_agent || 'User agent tidak tersedia' }}</span>
                        <span class="text-ink-faint">{{ session.is_current ? 'Aktif sekarang' : formatTime(session.last_activity) }}</span>
                    </div>

                    <div v-if="!sessions.length" class="px-4 py-8 text-center text-xs text-ink-soft">
                        Data sesi belum tersedia.
                    </div>
                </div>

                <form class="flex flex-col gap-2 border-t border-line p-4 sm:max-w-xl sm:flex-row" @submit.prevent="revokeOthers">
                    <input
                        v-model="sessionsForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Password saat ini"
                        class="ui-control ui-focus-ring min-w-0 flex-1 px-3 text-sm"
                    >
                    <Button variant="secondary" :disabled="sessionsForm.processing">
                        Keluarkan sesi lain
                    </Button>
                </form>
                <p v-if="sessionsForm.errors.current_password" class="-mt-2 px-4 pb-4 text-xs text-danger">
                    {{ sessionsForm.errors.current_password }}
                </p>
            </section>
        </div>
    </AdminLayout>
</template>
