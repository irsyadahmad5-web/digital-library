<script setup lang="ts">
import DialogShell from '@/components/ui/dialog/DialogShell.vue';
import { Button } from '@/components/ui/button';
const props = withDefaults(defineProps<{ open: boolean; title: string; description: string; confirmLabel?: string; cancelLabel?: string; destructive?: boolean; busy?: boolean }>(), { confirmLabel: 'Lanjutkan', cancelLabel: 'Batal', destructive: false, busy: false });
const emit = defineEmits<{ 'update:open': [value: boolean]; confirm: [] }>();
</script>
<template>
    <DialogShell :open="open" :title="title" :description="description" @update:open="emit('update:open', $event)">
        <slot />
        <template #footer>
            <Button variant="secondary" :disabled="busy" @click="emit('update:open', false)">{{ cancelLabel }}</Button>
            <Button :variant="destructive ? 'danger' : 'primary'" :disabled="busy" @click="emit('confirm')">{{ busy ? 'Memproses…' : confirmLabel }}</Button>
        </template>
    </DialogShell>
</template>
