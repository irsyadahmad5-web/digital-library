<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    Eye,
    EyeOff,
    LockKeyhole,
    LogIn,
    Mail,
} from '@lucide/vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Login Admin" />

    <AuthLayout
        eyebrow="Administration"
        title="Masuk ke Admin"
        description="Gunakan akun administrator yang terdaftar untuk melanjutkan."
    >
        <Alert v-if="page.props.flash.status" tone="success" :title="page.props.flash.status" />

        <form class="grid gap-4" :class="page.props.flash.status ? 'mt-4' : ''" @submit.prevent="submit">
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-ink">Email</span>
                <div class="ui-control ui-focus-ring flex min-h-11 items-center px-3">
                    <Mail class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        autofocus
                        class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none placeholder:text-ink-faint"
                        placeholder="admin@example.com"
                    >
                </div>
                <p v-if="form.errors.email" class="text-xs leading-5 text-danger" role="alert">{{ form.errors.email }}</p>
            </label>

            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-ink">Password</span>
                <div class="ui-control ui-focus-ring flex min-h-11 items-center px-3">
                    <LockKeyhole class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none placeholder:text-ink-faint"
                        placeholder="••••••••••"
                    >
                    <button
                        type="button"
                        class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] text-ink-faint hover:bg-surface-subtle hover:text-ink"
                        :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
                <p v-if="form.errors.password" class="text-xs leading-5 text-danger" role="alert">{{ form.errors.password }}</p>
            </label>

            <div class="flex min-h-9 items-center justify-between gap-4">
                <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-ink-soft">
                    <Checkbox v-model="form.remember" />
                    Ingat saya
                </label>

                <Link href="/admin/forgot-password" class="text-xs font-semibold text-brand hover:underline">
                    Lupa password?
                </Link>
            </div>

            <Button class="mt-1 w-full" size="large" :disabled="form.processing">
                <LogIn class="size-4" />
                {{ form.processing ? 'Memproses…' : 'Masuk' }}
            </Button>
        </form>

        <template #footer>
            Pembaca tidak memerlukan login untuk mengakses koleksi publik.
        </template>
    </AuthLayout>
</template>
