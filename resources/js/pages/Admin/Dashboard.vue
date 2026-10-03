<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="flex flex-col gap-6">
            <div>
                <p class="text-sm font-medium text-primary">Admin</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                    Selamat datang, {{ page.props.auth.user?.name }}
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Authentication dan security foundation sudah aktif.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="[label, value] in [
                        ['Total Ebook', '0'],
                        ['Dibaca', '0'],
                        ['Download', '0'],
                        ['Storage', '0 GB'],
                    ]"
                    :key="label"
                    class="rounded-2xl border border-border bg-surface p-5"
                >
                    <p class="text-sm text-muted-foreground">{{ label }}</p>
                    <p class="mt-2 text-3xl font-semibold">{{ value }}</p>
                </article>
            </div>

            <section class="rounded-2xl border border-border bg-surface p-6">
                <div class="flex items-start gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <ShieldCheck class="size-5" />
                    </div>
                    <div>
                        <h2 class="font-semibold">Security baseline aktif</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground">
                            Login rate limit, session regeneration, permission middleware, audit logging, reset password, dan session revocation telah disiapkan.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
