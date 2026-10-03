<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { BookOpen, LockKeyhole, Mail } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const siteName = page.props.site.general.site_name || 'Digital Library';
const logoUrl = page.props.site.general.logo_url;

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

    <main class="min-h-dvh bg-background px-5 py-10 text-foreground">
        <div class="mx-auto grid min-h-[calc(100dvh-5rem)] max-w-6xl items-center gap-10 lg:grid-cols-[1.05fr_.95fr]">
            <section class="hidden lg:block">
                <div class="max-w-xl">
                    <div class="mb-6 inline-flex size-12 items-center justify-center overflow-hidden rounded-2xl bg-primary text-primary-foreground">
                        <img v-if="logoUrl" :src="String(logoUrl)" alt="" class="size-full object-cover">
                        <BookOpen v-else class="size-6" />
                    </div>
                    <p class="text-sm font-semibold text-primary">{{ siteName }} Admin</p>
                    <h1 class="mt-3 text-5xl font-semibold leading-tight tracking-tight">
                        Kelola perpustakaan dengan tenang dan aman.
                    </h1>
                    <p class="mt-6 max-w-lg text-lg leading-8 text-muted-foreground">
                        Area administrasi terpisah dari halaman publik. Pembaca tetap bisa membaca dan mengunduh ebook tanpa login.
                    </p>
                </div>
            </section>

            <section class="mx-auto w-full max-w-md rounded-3xl border border-border bg-surface p-6 shadow-sm sm:p-8">
                <div class="mb-8">
                    <div class="mb-5 inline-flex size-11 items-center justify-center overflow-hidden rounded-2xl bg-primary/10 text-primary lg:hidden">
                        <img v-if="logoUrl" :src="String(logoUrl)" alt="" class="size-full object-cover">
                        <BookOpen v-else class="size-5" />
                    </div>
                    <h2 class="text-2xl font-semibold tracking-tight">Masuk ke Admin</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Gunakan akun administrator yang terdaftar.
                    </p>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <label class="block">
                        <span class="mb-2 block text-sm font-medium">Email</span>
                        <div class="flex min-h-12 items-center gap-3 rounded-xl border border-border bg-background px-4 focus-within:ring-2 focus-within:ring-primary/25">
                            <Mail class="size-4 text-muted-foreground" />
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                autofocus
                                class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                                placeholder="admin@example.com"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-600">{{ form.errors.email }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium">Password</span>
                        <div class="flex min-h-12 items-center gap-3 rounded-xl border border-border bg-background px-4 focus-within:ring-2 focus-within:ring-primary/25">
                            <LockKeyhole class="size-4 text-muted-foreground" />
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                                placeholder="••••••••••"
                            />
                        </div>
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
                    </label>

                    <div class="flex items-center justify-between gap-4 text-sm">
                        <label class="inline-flex items-center gap-2 text-muted-foreground">
                            <input v-model="form.remember" type="checkbox" class="size-4 rounded border-border">
                            Ingat saya
                        </label>

                        <Link href="/admin/forgot-password" class="font-medium text-primary hover:underline">
                            Lupa password?
                        </Link>
                    </div>

                    <Button class="w-full" size="large" :disabled="form.processing">
                        {{ form.processing ? 'Memproses...' : 'Masuk' }}
                    </Button>
                </form>

                <div class="mt-7 border-t border-border pt-5 text-center">
                    <Link href="/" class="text-sm text-muted-foreground hover:text-foreground">
                        ← Kembali ke perpustakaan
                    </Link>
                </div>
            </section>
        </div>
    </main>
</template>
