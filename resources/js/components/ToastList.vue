<script setup>
import { useNotificationsStore } from '@/stores/notifications';

const notifications = useNotificationsStore();
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
        <div
            v-for="n in notifications.liste"
            :key="n.id"
            class="pointer-events-auto flex max-w-lg items-start gap-3 rounded-md px-4 py-3 text-sm shadow-lg"
            :class="n.type === 'erreur' ? 'bg-bad text-white' : 'bg-epi-anthracite text-white'"
            role="status"
        >
            <span class="flex-1">{{ n.message }}</span>
            <button type="button" class="opacity-80 hover:opacity-100" aria-label="×" @click="notifications.fermer(n.id)">×</button>
        </div>
    </div>
</template>
