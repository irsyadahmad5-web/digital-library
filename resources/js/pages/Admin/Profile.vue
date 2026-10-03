<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
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
        <div class="max-w-5xl">
            <div>
                <p class="text-sm font-medium text-primary">Akun</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">Profil & Keamanan</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Kelola identitas admin, password, dan sesi aktif.
                </p>
            </div>

            <div v-if="page.props.flash.status" class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.status }}
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-border bg-surface p-6">
                    <h2 class="font-semibold">Informasi profil</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Konfirmasi password saat mengubah data sensitif.
                    </p>

                    <form class="mt-6 space-y-4" @submit.prevent="updateProfile">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium">Nama</span>
                            <input v-model="profileForm.name" class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                            <p v-if="profileForm.errors.name" class="mt-2 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium">Email</span>
                            <input v-model="profileForm.email" type="email" class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                            <p v-if="profileForm.errors.email" class="mt-2 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium">Password saat ini</span>
                            <input v-model="profileForm.current_password" type="password" autocomplete="current-password" class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                            <p v-if="profileForm.errors.current_password" class="mt-2 text-sm text-red-600">{{ profileForm.errors.current_password }}</p>
                        </label>

                        <Button :disabled="profileForm.processing">Simpan profil</Button>
                    </form>

                    <div class="mt-6 border-t border-border pt-5 text-sm text-muted-foreground">
                        <p>Role: {{ profile.roles.join(', ') || '-' }}</p>
                        <p class="mt-1">Login terakhir: {{ profile.last_login_at || '-' }}</p>
                        <p class="mt-1">IP terakhir: {{ profile.last_login_ip || '-' }}</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-surface p-6">
                    <h2 class="font-semibold">Ganti password</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Minimal 10 karakter, huruf besar/kecil, angka, dan simbol.
                    </p>

                    <form class="mt-6 space-y-4" @submit.prevent="updatePassword">
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium">Password saat ini</span>
                            <input v-model="passwordForm.current_password" type="password" autocomplete="current-password" class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                            <p v-if="passwordForm.errors.current_password" class="mt-2 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium">Password baru</span>
                            <input v-model="passwordForm.password" type="password" autocomplete="new-password" class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                            <p v-if="passwordForm.errors.password" class="mt-2 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-sm font-medium">Ulangi password baru</span>
                            <input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" class="min-h-11 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                        </label>

                        <Button :disabled="passwordForm.processing">Perbarui password</Button>
                    </form>
                </section>
            </div>

            <section class="mt-6 rounded-2xl border border-border bg-surface p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="font-semibold">Sesi aktif</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Keluarkan perangkat lain bila ada sesi yang tidak dikenali.
                        </p>
                    </div>
                </div>

                <div class="mt-5 divide-y divide-border rounded-xl border border-border">
                    <div
                        v-for="session in sessions"
                        :key="session.id"
                        class="grid gap-2 p-4 text-sm sm:grid-cols-[140px_1fr_auto]"
                    >
                        <span>{{ session.ip_address || 'IP tidak tersedia' }}</span>
                        <span class="truncate text-muted-foreground">{{ session.user_agent || 'User agent tidak tersedia' }}</span>
                        <span class="text-muted-foreground">
                            {{ session.is_current ? 'Sesi ini' : formatTime(session.last_activity) }}
                        </span>
                    </div>

                    <div v-if="!sessions.length" class="p-4 text-sm text-muted-foreground">
                        Data sesi belum tersedia.
                    </div>
                </div>

                <form class="mt-5 flex flex-col gap-3 sm:max-w-md sm:flex-row" @submit.prevent="revokeOthers">
                    <input
                        v-model="sessionsForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Password saat ini"
                        class="min-h-11 min-w-0 flex-1 rounded-xl border border-border bg-background px-4 text-sm outline-none"
                    >
                    <Button variant="secondary" :disabled="sessionsForm.processing">
                        Keluarkan sesi lain
                    </Button>
                </form>
                <p v-if="sessionsForm.errors.current_password" class="mt-2 text-sm text-red-600">
                    {{ sessionsForm.errors.current_password }}
                </p>
            </section>
        </div>
    </AdminLayout>
</template>
