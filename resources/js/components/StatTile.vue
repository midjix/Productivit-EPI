<script setup>
defineProps({
    libelle: { type: String, required: true },
    valeur: { type: String, required: true },
    indice: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-lg bg-white/10 p-4">
        <p class="text-xs uppercase tracking-wide text-role-ink-muted">{{ libelle }}</p>
        <p class="mt-1 text-2xl font-semibold">{{ valeur }}</p>
        <p v-if="indice" class="mt-1 text-xs text-role-ink-muted">{{ indice }}</p>
    </div>
</template>
