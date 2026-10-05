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
        <section class="mx-auto px-5 py-12 sm:px-8 sm:py-16" style="max-width: var(--content-max-width)">
            <div class="mx-auto max-w-3xl">
                <p class="text-sm font-medium text-primary">Informasi</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight">{{ title }}</h1>

                <div class="mt-8 rounded-3xl border border-border bg-surface p-6 sm:p-8">
                    <div v-if="organization" class="flex items-start gap-3">
                        <Building2 class="mt-0.5 size-5 shrink-0 text-primary" />
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pengelola</p>
                            <p class="mt-1 font-semibold">{{ organization }}</p>
                        </div>
                    </div>

                    <p
                        v-if="description"
                        class="whitespace-pre-line text-base leading-8 text-muted-foreground"
                        :class="organization ? 'mt-7' : ''"
                    >
                        {{ description }}
                    </p>

                    <div v-if="kind === 'contact' && contact" class="mt-8 grid gap-4">
                        <div v-if="contact.address" class="flex items-start gap-3 rounded-2xl bg-muted/60 p-4">
                            <MapPin class="mt-0.5 size-5 shrink-0 text-primary" />
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Alamat</p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-6">{{ contact.address }}</p>
                            </div>
                        </div>
                        <a
                            v-if="contact.phone"
                            :href="`tel:${contact.phone}`"
                            class="flex items-start gap-3 rounded-2xl bg-muted/60 p-4 hover:bg-muted"
                        >
                            <Phone class="mt-0.5 size-5 shrink-0 text-primary" />
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Telepon</p>
                                <p class="mt-1 text-sm">{{ contact.phone }}</p>
                            </div>
                        </a>
                        <a
                            v-if="contact.email"
                            :href="`mailto:${contact.email}`"
                            class="flex items-start gap-3 rounded-2xl bg-muted/60 p-4 hover:bg-muted"
                        >
                            <Mail class="mt-0.5 size-5 shrink-0 text-primary" />
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Email</p>
                                <p class="mt-1 text-sm">{{ contact.email }}</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
