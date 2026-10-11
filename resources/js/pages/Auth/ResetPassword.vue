<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Eye,
    EyeOff,
    KeyRound,
    Mail,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{
    token: string;
    email: string;
}>();

const showPassword = ref(false);
const showConfirmation = ref(false);

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

    <AuthLayout
        eyebrow="Account recovery"
        title="Buat password baru"
        description="Gunakan minimal 10 karakter dengan kombinasi huruf besar, huruf kecil, angka, dan simbol."
    >
        <form class="grid gap-4" @submit.prevent="submit">
            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-ink">Email</span>
                <div class="ui-control ui-focus-ring flex min-h-11 items-center px-3">
                    <Mail class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none"
                    >
                </div>
                <p v-if="form.errors.email" class="text-xs leading-5 text-danger" role="alert">{{ form.errors.email }}</p>
            </label>

            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-ink">Password baru</span>
                <div class="ui-control ui-focus-ring flex min-h-11 items-center px-3">
                    <KeyRound class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none"
                    >
                    <button
                        type="button"
                        class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] text-ink-faint hover:bg-surface-subtle hover:text-ink"
                        :aria-label="showPassword ? 'Sembunyikan password baru' : 'Tampilkan password baru'"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
                <p v-if="form.errors.password" class="text-xs leading-5 text-danger" role="alert">{{ form.errors.password }}</p>
            </label>

            <label class="grid gap-1.5">
                <span class="text-xs font-semibold text-ink">Ulangi password</span>
                <div class="ui-control ui-focus-ring flex min-h-11 items-center px-3">
                    <KeyRound class="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    <input
                        v-model="form.password_confirmation"
                        :type="showConfirmation ? 'text' : 'password'"
                        autocomplete="new-password"
                        class="min-w-0 flex-1 bg-transparent px-2.5 text-sm text-ink outline-none"
                    >
                    <button
                        type="button"
                        class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] text-ink-faint hover:bg-surface-subtle hover:text-ink"
                        :aria-label="showConfirmation ? 'Sembunyikan konfirmasi password' : 'Tampilkan konfirmasi password'"
                        @click="showConfirmation = !showConfirmation"
                    >
                        <EyeOff v-if="showConfirmation" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
            </label>

            <Button class="w-full" size="large" :disabled="form.processing">
                <KeyRound class="size-4" />
                {{ form.processing ? 'Menyimpan…' : 'Simpan password baru' }}
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
