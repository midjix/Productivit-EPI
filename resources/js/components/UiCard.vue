<script setup>
defineProps({
    titre: { type: String, required: true },
    sousTitre: { type: String, default: '' },
});
</script>

<template>
    <section class="rounded-lg border border-border bg-surface-raised p-5 shadow-sm">
        <header class="mb-4">
            <h3 class="text-base font-medium uppercase tracking-wide text-ink">{{ titre }}</h3>
            <p v-if="sousTitre" class="text-sm text-ink-muted">{{ sousTitre }}</p>
        </header>
        <slot />
    </section>
</template>
