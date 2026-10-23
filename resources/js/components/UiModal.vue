<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

/** Boîte de dialogue simple (formulaire d'édition) : fermeture par Échap, par le voile ou le bouton. */
defineProps({ titre: { type: String, required: true } });
const emit = defineEmits(['fermer']);

function surTouche(e) {
    if (e.key === 'Escape') emit('fermer');
}

onMounted(() => document.addEventListener('keydown', surTouche));
onBeforeUnmount(() => document.removeEventListener('keydown', surTouche));
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" @click.self="emit('fermer')">
        <div role="dialog" aria-modal="true" :aria-label="titre" class="w-full max-w-lg rounded-lg border border-border bg-surface-raised p-5 shadow-2xl">
            <header class="mb-4 flex items-start justify-between gap-4">
                <h3 class="text-base font-medium uppercase tracking-wide text-ink">{{ titre }}</h3>
                <button type="button" class="rounded-md px-2 text-xl leading-none text-ink-muted hover:text-ink" aria-label="×" @click="emit('fermer')">×</button>
            </header>
            <slot />
        </div>
    </div>
</template>
