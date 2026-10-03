<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const form = useForm({ email: '' });

function submit() {
    form.post('/admin/forgot-password');
}
</script>

<template>
    <Head title="Lupa Password" />

    <main class="flex min-h-dvh items-center justify-center bg-background px-5 py-10">
        <section class="w-full max-w-md rounded-3xl border border-border bg-surface p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-semibold tracking-tight">Reset password</h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Masukkan email admin. Jika akun terdaftar, sistem akan mengirim tautan reset.
            </p>

            <div v-if="page.props.flash.status" class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.status }}
            </div>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium">Email</span>
                    <div class="flex min-h-12 items-center gap-3 rounded-xl border border-border bg-background px-4">
                        <Mail class="size-4 text-muted-foreground" />
                        <input v-model="form.email" type="email" autocomplete="email" class="min-w-0 flex-1 bg-transparent text-sm outline-none">
                    </div>
                    <p v-if="form.errors.email" class="mt-2 text-sm text-red-600">{{ form.errors.email }}</p>
                </label>

                <Button class="w-full" size="large" :disabled="form.processing">
                    Kirim tautan reset
                </Button>
            </form>

            <Link href="/admin/login" class="mt-6 block text-center text-sm text-muted-foreground hover:text-foreground">
                ← Kembali ke login
            </Link>
        </section>
    </main>
</template>
