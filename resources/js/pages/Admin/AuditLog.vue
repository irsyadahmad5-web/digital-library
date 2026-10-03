<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

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
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Audit Log" />

    <AdminLayout>
        <div>
            <p class="text-sm font-medium text-primary">Security</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Audit Log</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Riwayat kejadian keamanan dan perubahan akun administrator.
            </p>
        </div>

        <div class="mt-8 overflow-hidden rounded-2xl border border-border bg-surface">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-border bg-muted/70 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Aktor</th>
                            <th class="px-4 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="whitespace-nowrap px-4 py-4 text-muted-foreground">{{ formatDate(log.created_at) }}</td>
                            <td class="px-4 py-4 font-medium">{{ log.event }}</td>
                            <td class="px-4 py-4">
                                <template v-if="log.actor">
                                    <div>{{ log.actor.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ log.actor.email }}</div>
                                </template>
                                <span v-else class="text-muted-foreground">Sistem / guest</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-muted-foreground">{{ log.ip_address || '-' }}</td>
                        </tr>

                        <tr v-if="!logs.data.length">
                            <td colspan="4" class="px-4 py-12 text-center text-muted-foreground">
                                Belum ada audit log.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-border px-4 py-3 text-sm">
                <span class="text-muted-foreground">Halaman {{ logs.current_page }} dari {{ logs.last_page }}</span>
                <div class="flex gap-2">
                    <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="rounded-lg border border-border px-3 py-2">Sebelumnya</Link>
                    <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="rounded-lg border border-border px-3 py-2">Berikutnya</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
