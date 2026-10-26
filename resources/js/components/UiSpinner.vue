<script setup>
/** Anneau tournant : signale qu'un chargement est en cours (la page n'est pas figée). */
defineProps({
    taille: { type: String, default: 'h-6 w-6' },
    libelle: { type: String, default: '' },
    couleur: { type: String, default: 'text-accent' }, // `text-current` sur un fond coloré
});
</script>

<template>
    <span class="inline-flex items-center gap-2" role="status" :aria-label="libelle || undefined">
        <svg :class="[taille, couleur, 'animate-spin']" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-opacity="0.25" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <span v-if="libelle">{{ libelle }}</span>
    </span>
</template>
