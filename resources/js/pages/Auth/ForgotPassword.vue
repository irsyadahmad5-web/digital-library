<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Mail, Send } from '@lucide/vue';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { SharedPageProps } from '@/types';

const page = usePage<SharedPageProps>();
const form = useForm({ email: '' });

function submit() {
    form.post('/admin/forgot-password');
}
</script>

<template>
    <Head title="Lupa Password" />

    <AuthLayout
        eyebrow="Account recovery"
        title="Reset password"
        description="Masukkan email admin. Jika akun terdaftar, sistem akan mengirim tautan reset."
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
                        autocomplete="email"
                        autofocus
                        class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none placeholder:text-ink-faint"
                        placeholder="admin@example.com"
                    >
                </div>
                <p v-if="form.errors.email" class="text-xs leading-5 text-danger" role="alert">{{ form.errors.email }}</p>
            </label>

            <Button class="w-full" size="large" :disabled="form.processing">
                <Send class="size-4" />
                {{ form.processing ? 'Mengirim…' : 'Kirim tautan reset' }}
            </Button>
        </form>

        <template #footer>
            <Link href="/admin/login" class="inline-flex items-center gap-1.5 font-semibold text-ink-soft hover:text-ink">
                <ArrowLeft class="size-3.5" />
                Kembali ke login
            </Link>
        </template>
    </AuthLayout>
</template>
