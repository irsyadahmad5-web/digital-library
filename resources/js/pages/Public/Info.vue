<script setup lang="ts">
import { Building2, Mail, MapPin, Phone } from '@lucide/vue';
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SeoPayload } from '@/types/seo';

defineProps<{
    kind: 'about' | 'contact';
    title: string;
    description: string;
    organization: string;
    seo: SeoPayload;
    contact: {
        address: string;
        phone: string;
        email: string;
    } | null;
}>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="ui-page-shell py-9 sm:py-12">
            <div class="mx-auto max-w-4xl">
                <div class="border-b border-line pb-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-brand">Informasi</p>
                    <h1 class="mt-1.5 text-balance text-3xl font-semibold tracking-[-0.025em] text-ink sm:text-4xl">{{ title }}</h1>
                </div>

                <div class="grid gap-7 pt-7" :class="kind === 'contact' && contact ? 'lg:grid-cols-[minmax(0,1fr)_300px]' : ''">
                    <div class="min-w-0">
                        <div v-if="organization" class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-md)] bg-brand-soft text-brand">
                                <Building2 class="size-4" />
                            </span>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">Pengelola</p>
                                <p class="mt-1 text-sm font-semibold text-ink">{{ organization }}</p>
                            </div>
                        </div>

                        <p
                            v-if="description"
                            class="ui-reading-measure whitespace-pre-line text-[15px] leading-7 text-ink-soft"
                            :class="organization ? 'mt-6' : ''"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <aside v-if="kind === 'contact' && contact" class="h-fit rounded-[var(--radius-lg)] border border-line bg-surface">
                        <div class="border-b border-line px-4 py-3">
                            <p class="text-xs font-semibold text-ink">Kontak perpustakaan</p>
                        </div>

                        <div class="divide-y divide-line">
                            <div v-if="contact.address" class="flex items-start gap-3 px-4 py-3">
                                <MapPin class="mt-0.5 size-4 shrink-0 text-brand" />
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Alamat</p>
                                    <p class="mt-1 whitespace-pre-line text-xs leading-5 text-ink">{{ contact.address }}</p>
                                </div>
                            </div>

                            <a
                                v-if="contact.phone"
                                :href="'tel:' + contact.phone"
                                class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-surface-subtle"
                            >
                                <Phone class="mt-0.5 size-4 shrink-0 text-brand" />
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Telepon</p>
                                    <p class="mt-1 text-xs font-medium text-ink">{{ contact.phone }}</p>
                                </div>
                            </a>

                            <a
                                v-if="contact.email"
                                :href="'mailto:' + contact.email"
                                class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-surface-subtle"
                            >
                                <Mail class="mt-0.5 size-4 shrink-0 text-brand" />
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-ink-faint">Email</p>
                                    <p class="mt-1 break-all text-xs font-medium text-ink">{{ contact.email }}</p>
                                </div>
                            </a>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
