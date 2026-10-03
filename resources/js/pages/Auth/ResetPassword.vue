<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/admin/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Buat Password Baru" />

    <main class="flex min-h-dvh items-center justify-center bg-background px-5 py-10">
        <section class="w-full max-w-md rounded-3xl border border-border bg-surface p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-semibold tracking-tight">Buat password baru</h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Minimal 10 karakter dan gunakan kombinasi huruf besar, huruf kecil, angka, serta simbol.
            </p>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium">Email</span>
                    <input v-model="form.email" type="email" autocomplete="email" class="min-h-12 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                    <p v-if="form.errors.email" class="mt-2 text-sm text-red-600">{{ form.errors.email }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium">Password baru</span>
                    <input v-model="form.password" type="password" autocomplete="new-password" class="min-h-12 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                    <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium">Ulangi password</span>
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="min-h-12 w-full rounded-xl border border-border bg-background px-4 text-sm outline-none">
                </label>

                <Button class="w-full" size="large" :disabled="form.processing">
                    Simpan password baru
                </Button>
            </form>

            <Link href="/admin/login" class="mt-6 block text-center text-sm text-muted-foreground hover:text-foreground">
                ← Kembali ke login
            </Link>
        </section>
    </main>
</template>
