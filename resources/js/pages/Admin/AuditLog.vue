<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ScrollText } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';

interface AuditItem {
    id: number;
    event: string;
    actor: { name: string; email: string } | null;
    subject_type: string | null;
    subject_id: string | null;
    ip_address: string | null;
    user_agent: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
}

interface PaginatedLogs {
    data: AuditItem[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{ logs: PaginatedLogs }>();

function formatDate(value: string | null) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Audit Log" />

    <AdminLayout>
        <div class="grid gap-5">
            <PageHeader
                eyebrow="System"
                title="Audit Log"
                description="Riwayat kejadian keamanan dan perubahan akun administrator."
            />

            <section class="overflow-hidden rounded-[var(--radius-lg)] border border-line bg-surface">
                <div v-if="logs.data.length" class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-left text-xs">
                        <thead class="border-b border-line bg-surface-subtle text-[10px] uppercase tracking-[0.06em] text-ink-faint">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Waktu</th>
                                <th class="px-4 py-3 font-semibold">Event</th>
                                <th class="px-4 py-3 font-semibold">Aktor</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="log in logs.data" :key="log.id" class="transition-colors hover:bg-surface-subtle/60">
                                <td class="whitespace-nowrap px-4 py-3 text-[11px] text-ink-faint">{{ formatDate(log.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <Badge tone="brand">{{ log.event }}</Badge>
                                    <p v-if="log.subject_type || log.subject_id" class="mt-1.5 text-[10px] text-ink-faint">
                                        {{ log.subject_type || 'Subject' }}<template v-if="log.subject_id"> · #{{ log.subject_id }}</template>
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <template v-if="log.actor">
                                        <p class="font-semibold text-ink">{{ log.actor.name }}</p>
                                        <p class="mt-0.5 text-[11px] text-ink-faint">{{ log.actor.email }}</p>
                                    </template>
                                    <span v-else class="text-ink-soft">Sistem / guest</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-[11px] text-ink-soft">{{ log.ip_address || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.data.length" class="divide-y divide-line md:hidden">
                    <article v-for="log in logs.data" :key="log.id" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <Badge tone="brand">{{ log.event }}</Badge>
                                <p class="mt-2 text-xs font-semibold text-ink">
                                    {{ log.actor?.name || 'Sistem / guest' }}
                                </p>
                                <p v-if="log.actor?.email" class="mt-0.5 truncate text-[11px] text-ink-faint">{{ log.actor.email }}</p>
                            </div>
                            <time class="shrink-0 text-right text-[10px] leading-4 text-ink-faint">{{ formatDate(log.created_at) }}</time>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-line pt-3 text-[11px] text-ink-soft">
                            <span>IP: <span class="font-mono">{{ log.ip_address || '—' }}</span></span>
                            <span v-if="log.subject_type || log.subject_id">
                                {{ log.subject_type || 'Subject' }}<template v-if="log.subject_id"> #{{ log.subject_id }}</template>
                            </span>
                        </div>
                    </article>
                </div>

                <EmptyState
                    v-if="!logs.data.length"
                    title="Belum ada audit log"
                    description="Kejadian keamanan dan perubahan akun akan muncul di sini."
                >
                    <template #icon><ScrollText class="size-5" /></template>
                </EmptyState>

                <div class="flex items-center justify-between gap-3 border-t border-line px-4 py-3 text-xs">
                    <span class="text-ink-soft">Halaman {{ logs.current_page }} dari {{ logs.last_page }}</span>
                    <div v-if="logs.last_page > 1" class="flex gap-1.5">
                        <Button v-if="logs.prev_page_url" as-child size="small" variant="secondary">
                            <Link :href="logs.prev_page_url">Sebelumnya</Link>
                        </Button>
                        <Button v-if="logs.next_page_url" as-child size="small" variant="secondary">
                            <Link :href="logs.next_page_url">Berikutnya</Link>
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
